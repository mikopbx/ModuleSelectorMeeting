<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 10 2020
 */
namespace Modules\ModuleSelectorMeeting\bin;
require_once 'Globals.php';

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\Asterisk\AsteriskManager;
use MikoPBX\Core\System\Processes;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\Core\System\Util;
use Modules\ModuleRHVoice\Models\ModuleRHVoice;
use Modules\ModuleSelectorMeeting\Lib\Logger;
use Modules\ModuleSelectorMeeting\Lib\SelectorMeetingConf;
use Modules\ModuleSelectorMeeting\Models\MeetingRules;

class AmiConfClient extends WorkerBase
{
    /** @var AsteriskManager $am */
    protected AsteriskManager $am;
    protected Logger $logger;

    protected array $confUsers = [];

    /**
     * Метки времени «висящих» приглашений: [extension][number] => timestamp.
     * Защищает от повторного набора участника, которому уже звонят,
     * но он ещё не снял трубку (не вошёл в конференцию).
     */
    protected array $pendingInvites = [];

    /** Таймаут дозвона, в секундах: в его пределах повторный Originate не делаем. */
    protected const INVITE_TIMEOUT = 40;

    /**
     * Старт работы листнера.
     *
     * @param $argv
     */
    public function start($argv):void
    {
        $this->logger =  new Logger('AmiConfClient', 'ModuleSelectorMeeting');
        $this->logger->writeInfo('Starting '. basename(__CLASS__).'...');
        $this->updateActiveUsers();

        $this->am = Util::getAstManager();
        $this->setFilter();
        $this->am->addEventHandler("ConfbridgeJoin",  [$this, "callback"]);
        $this->am->addEventHandler("ConfbridgeLeave", [$this, "callback"]);
        $this->am->addEventHandler("userevent",       [$this, "userEvent"]);
        while (true) {
            $result = $this->am->waitUserEvent(true);
            if (!$result) {
                usleep(100000);
                $this->am = Util::getAstManager();
                $this->setFilter();
            }
        }
    }

    /**
     * Получение сведений об активных конференциях.
     * @return void
     */
    private function updateActiveUsers():void
    {
        $rules = MeetingRules::find(['columns' => ['extension']]);
        foreach ($rules as $rule){
            $asteriskPath = Util::which('asterisk');
            $grepPath   = Util::which('grep');
            $sedPath    = Util::which('sed');
            $result     = trim(shell_exec("$asteriskPath -rx 'confbridge list $rule->extension' | $grepPath PJSIP/ | $sedPath 's|.*/\(.*\)-.*|\\1|'")??'');
            if(!empty($result)){
                $array = explode("\n", trim($result));
                $this->confUsers[$rule->extension] = array_combine($array, $array);
            }
        }
        $this->logger->writeInfo($this->confUsers, 'confUsers');
    }

    /**
     * Установка фильтра
     *
     */
    private function setFilter():void
    {
        $pingTube = $this->makePingTubeName(self::class);
        $this->am->sendRequestTimeout('Filter', ['Operation' => 'Add', 'Filter' => 'UserEvent: '.$pingTube]);
        $this->am->sendRequestTimeout('Filter', ['Operation' => 'Add', 'Filter' => 'UserEvent: SelectorMeetingInvite']);
        $this->am->sendRequestTimeout('Filter', ['Operation' => 'Add', 'Filter' => 'Event: ConfbridgeJoin']);
        $this->am->sendRequestTimeout('Filter', ['Operation' => 'Add', 'Filter' => 'Event: ConfbridgeLeave']);
    }

    public function userEvent($parameters):void
    {
        if($parameters['UserEvent'] === 'SelectorMeetingInvite'){
            $this->logger->writeInfo($parameters, 'USER_EVENT');
            $this->inviteUsers($parameters['extension']);
        }
        $this->replyOnPingRequest($parameters);
    }

    /**
     * Функция обработки оповещений.
     *
     * @param $parameters
     */
    public function callback($parameters):void{
        global $argv;
        $script = dirname($argv[0], 2) ."/agi-bin/alertScript.php";

        $this->logger->rotate();
        $this->logger->writeInfo($parameters, 'EVENT');
        if( stripos($parameters['Channel'], 'PJSIP') === false ||
            $parameters['Context'] !== SelectorMeetingConf::CONTEXT_MEETING){
            return;
        }
        // Номер участника берём из имени канала: сегмент между "/" и первым "-".
        // Устойчиво к транспортам: PJSIP/201-0, PJSIP/201-WS-0, PJSIP/201-TLS-0.
        $userNumber  = '';
        $channelName = $parameters['Channel'] ?? '';
        if (strpos($channelName, '/') !== false) {
            $peer       = explode('/', $channelName)[1];
            $userNumber = explode('-', $peer)[0];
        }
        $extension  = $parameters['Conference'];
        // Участник вошёл/вышел — приглашение больше не «висит».
        unset($this->pendingInvites[$extension][$userNumber]);
        if($parameters['Event']==="ConfbridgeLeave"){
            unset($this->confUsers[$extension][$userNumber]);
            return;
        }
        $this->confUsers[$extension][$userNumber] = $userNumber;
        $countUsers = count($this->confUsers[$extension]);

        if($parameters['Admin'] === 'Yes'){
            $this->inviteUsers($extension);
        }
        $this->logger->writeInfo('Start alert '.$extension.', '.$script, 'EVENT');
        $this->am->Originate(
            "Local/$extension@internal/n",
            's',
            SelectorMeetingConf::CONTEXT_MEETING,
            1,
            'AGI',
            "$script,alert,$userNumber,$countUsers",
            '60',
            'ALERT',
            'alert=1',
            '',
            '1');
    }

    /**
     * Запускаем Originate участникам конференции.
     * @param $extension
     * @return void
     */
    public function inviteUsers($extension):void{
        $ruleData = MeetingRules::findFirst("extension='$extension'");
        if(!$ruleData){
            $this->logger->writeInfo("Rule not found - $extension", 'EVENT');
            return;
        }
        $usersIdArray = explode(',', $ruleData->usersId);
        $filter = [
            "type = 'SIP' AND userid IN ({userid:array})",
            'columns' => ['userid', 'number'],
            'bind' => [
                'userid' => $usersIdArray
            ],
        ];
        $users  = Extensions::find($filter);
        $now = time();
        foreach ($users as $extensionData){
            $number = $extensionData->number;
            if(isset($this->confUsers[$extension][$number])){
                $this->logger->writeInfo("User $number already in meeting $extension", 'inviteUsers');
                continue;
            }
            $pendingAt = $this->pendingInvites[$extension][$number] ?? 0;
            if($pendingAt !== 0 && ($now - $pendingAt) < self::INVITE_TIMEOUT){
                $this->logger->writeInfo("User $number is already being called to $extension", 'inviteUsers');
                continue;
            }
            try {
                $this->logger->writeInfo("Start invite $number to $extension", 'inviteUsers');
                $this->pendingInvites[$extension][$number] = $now;
                Util::amiOriginate($number, '', $extension);
            }catch (\Exception $e){
                unset($this->pendingInvites[$extension][$number]);
                $this->logger->writeError($e->getMessage(), 'inviteUsers');
            }
        }
    }

    public static function tts($text):string
    {
        $dir = dirname(__DIR__).'/db/media';
        Util::mwMkdir($dir);

        $port  = '8081';
        $voice = 'vitaliy-ng';
        $rate  = '40';
        if(class_exists('Modules\ModuleRHVoice\Models\ModuleRHVoice')) {
            $settings = ModuleRHVoice::findFirst();
            if($settings){
                $port = $settings->local_port;
                $voice= $settings->voice;
                $rate = $settings->rate;
            }
        }
        $filename = md5($text.$voice);
        $fullName   = "$dir/{$filename}_src.wav";
        $n_filename = "$dir/{$filename}.wav";

        if(!file_exists($n_filename)){
            $url = "http://127.0.0.1:$port/say?voice=$voice&rate=$rate&format=wav&text=".rawurlencode($text);
            shell_exec("/usr/bin/curl -o $fullName '$url'");
            $soxPath      = Util::which('sox');
            Processes::mwExec("/usr/bin/timeout 10 $soxPath -v 0.99 -G '$fullName' -c 1 -r 8000 -b 16 '$n_filename'");
        }
        unlink($fullName);
        return Util::trimExtensionForFile($n_filename);
    }

}

$action = $argv[1]??'';
if($action === 'start' && Util::getFilePathByClassName(AmiConfClient::class) === $argv[0]){
    // Start worker process
    AmiConfClient::startWorker($argv??null);
}