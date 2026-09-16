<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 12 2019
 */


namespace Modules\ModuleSelectorMeeting\Lib;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\System\Configs\PbxConf;
use MikoPBX\Core\System\Processes;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleSelectorMeeting\bin\AmiConfClient;
use Modules\ModuleSelectorMeeting\Models\MeetingRules;

class SelectorMeetingConf extends ConfigClass
{

    public const CONTEXT_MEETING = 'selector-meeting';

    /**
     * Собственный контекст исходящего дозвона участников конференции.
     * Это копия ядрового [internal-originate] БЕЗ строки
     * Gosub(interception_start,...): сохраняются резолв контактов
     * (set-dial-contacts, множественная регистрация) и редирект реального
     * PJSIP-канала в конференцию, но не создаётся служебная CDR-строка
     * ORIGINATE_TRY_DIAL. Core при этом не правится.
     */
    public const CONTEXT_ORIGINATE = 'internal-originate-selector-conf';

    /**
     * Receive information about mikopbx main database changes
     *
     * @param $data
     */
    public function modelsEventChangeData($data): void
    {
        $phpPath = Util::which('php');
        $moduleModels = [
            MeetingRules::class,
        ];
        if (in_array($data['model'], $moduleModels, true)){
            Processes::mwExecBg("$phpPath -f $this->moduleDir/bin/reloadCron.php", '/dev/null', 3);
        }
    }

    /**
     * Returns module workers to start it at WorkerSafeScriptCore
     *
     * @return array
     */
    public function getModuleWorkers(): array
    {
        return [
            [
                'type'   => WorkerSafeScriptsCore::CHECK_BY_AMI,
                'worker' => AmiConfClient::class,
            ],
        ];
    }

    /**
     * Prepares additional contexts sections in the extensions.conf file
     * @see https://docs.mikopbx.com/mikopbx-development/module-developement/module-class#extensiongencontexts
     *
     * @return string
     */
    public function extensionGenContexts(): string
    {
        $conf = "[".self::CONTEXT_MEETING."]". PHP_EOL.
            "exten => _X!,1,NoOp(---)" . PHP_EOL .
            "    same => n,ExecIf(\$[ \"\${alert}\" == \"1\" ]?Goto(start_conf))" . PHP_EOL .
            "    same => n,ExecIf(\$[ \"\${bridgePeer}\" != \"\${CHANNEL}\" && \"\${bridgePeer:0:5}\" != \"Local\" && \"\${bridgePeer}x\" != \"x\" ]?ChannelRedirect(\${bridgePeer},\${CONTEXT},\${EXTEN},\${PRIORITY}))" . PHP_EOL .
            "    same => n,ExecIf(\$[\"\${CHANNEL(channeltype)}\" == \"Local\"]?Hangup())" . PHP_EOL .
            "    same => n,AGI(meetme_dial.php)" . PHP_EOL .
            "    same => n,Answer()" . PHP_EOL .
            "    same => n,Gosub(set-answer-state,\${EXTEN},1)" . PHP_EOL .
            "    same => n,Set(CHANNEL(hangup_handler_wipe)=hangup_handler_meetme,s,1)" . PHP_EOL .
            "    same => n,Set(CONFBRIDGE(bridge,record_file)=\${MEETME_RECORDINGFILE}.wav)" . PHP_EOL .
            "    same => n,Set(CONFBRIDGE(bridge,record_file_timestamp)=false)" . PHP_EOL .
            "    same => n,Set(CONFBRIDGE(bridge,record_conference)=yes)" . PHP_EOL .
            "    same => n,Set(CONFBRIDGE(bridge,video_mode)=follow_talker)" . PHP_EOL .
            "    same => n,Set(CONFBRIDGE(user,talk_detection_events)=yes)" . PHP_EOL .
            '    same => n,ExecIf($[${CALLERID(name)} = ${MEETING_ADMIN}]?Set(CONFBRIDGE(user,admin)=yes))' . PHP_EOL .
            "    same => n(start_conf),Set(CONFBRIDGE(user,quiet)=yes)" . PHP_EOL .
            "    same => n,Set(CONFBRIDGE(user,music_on_hold_when_empty)=yes)" . PHP_EOL .
            "    same => n,ConfBridge(\${EXTEN})" . PHP_EOL .
            "    same => n,Hangup()" . PHP_EOL.
            "exten => s,1,Hangup()" . PHP_EOL;

        // Контекст исходящего дозвона без ORIGINATE_TRY_DIAL: копия
        // [internal-originate] без Gosub(interception_start,...).
        // Обзвон участников идёт через него (см. AmiConfClient::inviteUsers).
        $conf .= PHP_EOL . '['.self::CONTEXT_ORIGINATE.']' . PHP_EOL . <<<'CONF'
exten => _[0-9*#+a-zA-Z]!,1,Set(pt1c_cid=${FILTER(\*\#\+1234567890,${pt1c_cid})})
same => n,Set(MASTER_CHANNEL(ORIGINATE_DST_EXTEN)=${IF($["${pt1c_dst}x" == "x"]?${pt1c_cid}:${pt1c_dst})})
same => n,Set(number=${FILTER(\*\#\+1234567890,${EXTEN})})
same => n,ExecIf($["${EXTEN}" != "${number}"]?Goto(${CONTEXT},${number},$[${PRIORITY} + 1]))
same => n,Set(__IS_ORGNT=${EMPTY})
same => n,ExecIf($["${pt1c_cid}x" != "x"]?Set(CALLERID(num)=${pt1c_cid}))
same => n,ExecIf($["${origCidName}x" != "x"]?Set(CALLERID(name)=${origCidName}))
same => n,GosubIf($["${DIALPLAN_EXISTS(${CONTEXT}-custom,${EXTEN},1)}" == "1"]?${CONTEXT}-custom,${EXTEN},1)
same => n,ExecIf($["${SRC_QUEUE}x" != "x"]?Goto(internal-originate-queue,${EXTEN},1))
same => n,ExecIf($["${CUT(CHANNEL,\;,2)}" == "2"]?Set(__PT1C_SIP_HEADER=${SIPADDHEADER}))
same => n,ExecIf($["${PJSIP_ENDPOINT(${EXTEN},auth)}x" == "x"]?Goto(internal-num-undefined,${EXTEN},1))
same => n,Gosub(set-dial-contacts,${EXTEN},1)
same => n,ExecIf($["${FIELDQTY(DST_CONTACT,&)}" != "1" && "${ALLOW_MULTY_ANSWER}" != "1"]?Set(__PT1C_SIP_HEADER=${EMPTY_VAR}))
same => n,ExecIf($["${DST_CONTACT}x" != "x"]?Dial(${DST_CONTACT},${ringlength},TtekKHhb(originate-create-channel,${EXTEN},1)U(originate-answer-channel),s,1)))
exten => _[hit],1,Hangup
CONF;
        $conf .= PHP_EOL;

        return $conf;
    }


    /**
     * Generates the internal dialplan for IVR.
     *
     * @return string The generated internal dialplan.
     */
    public function extensionGenInternal(): string
    {
        $conf = '';
        $rules = MeetingRules::find();
        foreach ($rules as $rule) {
            if(empty($rule->extension)){
               continue;
            }
            $filter = [
                'userid=:user:',
                'bind' => [
                    'user' => $rule->meetingLeader
                ]
            ];
            $meetingLeader = 'x';
            $extension = Extensions::findFirst($filter);
            if($extension){
                $meetingLeader = $extension->number;
            }
            $conf .= "exten => $rule->extension,1,Set(MEETING_ADMIN=".$meetingLeader.")" . PHP_EOL;
            $conf .= "  same => n,Goto(".self::CONTEXT_MEETING.',${EXTEN},1)' . PHP_EOL;
        }
        return $conf;
    }

    /**
     *  Process CoreAPI requests under root rights
     *
     * @param array $request
     *
     * @return PBXApiResult
     */
    public function moduleRestAPICallback(array $request): PBXApiResult
    {
        $res    = new PBXApiResult();
        $res->processor = __METHOD__;
        $action = strtoupper($request['action']);
        switch ($action) {
            case 'CHECK':
                $templateMain = new SelectorMeetingMain();
                $res          = $templateMain->checkModuleWorkProperly();
                break;
            case 'RELOAD':
                $templateMain = new SelectorMeetingMain();
                $templateMain->startAllServices(true);
                $res->success = true;
                break;
            default:
                $res->success    = false;
                $res->messages[] = 'API action not found in moduleRestAPICallback ModuleSelectorMeeting';
        }

        return $res;
    }

    /**
     * Adds cron tasks.
     * @see https://docs.mikopbx.com/mikopbx-development/module-developement/module-class#createcrontasks
     *
     * @param array $tasks The array of cron tasks.
     *
     * @return void
     */
    public function createCronTasks(array &$tasks): void
    {
        $phpPath = Util::which('php');
        $data = MeetingRules::find()->toArray();
        foreach ($data as $item) {
            if(intval($item['enabledAutoCall']) !== 1){
                continue;
            }
            $id = $item['id'];
            $times = array_map('trim', explode(',', $item['at_time']));
            $daysOfWeek = [];
            for ($i = 1; $i <= 7; $i++) {
                if ($item["every$i"] == 1) {
                    // Преобразуем 7 (воскресенье) в 0
                    $daysOfWeek[] = $i % 7;
                }
            }
            if (!empty($daysOfWeek)) {
                $daysOfWeekStr = implode(',', $daysOfWeek);
                foreach ($times as $time) {
                    $timeParts = explode(':', $time);
                    $hour = $timeParts[0];
                    $minute = $timeParts[1];
                    $tasks[] = "$minute $hour * * $daysOfWeekStr $phpPath -f $this->moduleDir/bin/startMeeting.php $id > /dev/null 2>&1".PHP_EOL;
                }
            }
        }
    }

    /**
     * Process after enable action in web interface.
     * Перегенерируем диалплан, чтобы появился наш контекст
     * [internal-originate-selector-conf] и номера конференций в [internal].
     * @see https://docs.mikopbx.com/mikopbx-development/module-developement/module-class#onaftermoduleenable
     *
     * @return void
     */
    public function onAfterModuleEnable(): void
    {
        PbxConf::dialplanReload();
    }

    /**
     * Process after disable action in web interface.
     * Перегенерируем диалплан, чтобы убрать контексты модуля.
     * @see https://docs.mikopbx.com/mikopbx-development/module-developement/module-class#onaftermoduledisable
     *
     * @return void
     */
    public function onAfterModuleDisable(): void
    {
        PbxConf::dialplanReload();
    }
}