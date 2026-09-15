<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 11 2018
 */
namespace Modules\ModuleSelectorMeeting\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleSelectorMeeting\App\Forms\MeetingRulesForm;
use Modules\ModuleSelectorMeeting\App\Forms\ModuleSelectorMeetingForm;
use Modules\ModuleSelectorMeeting\Models\MeetingRules;
use Modules\ModuleSelectorMeeting\Models\ModuleSelectorMeeting;
use MikoPBX\Common\Models\Providers;

class ModuleSelectorMeetingController extends BaseController
{
    private $moduleUniqueID = 'ModuleSelectorMeeting';
    private $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.svg";
        $this->view->submitMode = null;
        parent::initialize();
    }

    /**
     * Index page controller
     */
    public function indexAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/$this->moduleUniqueID/module-selector-meeting-index.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/$this->moduleUniqueID/module-selector-meeting.css", true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);
        /*
        $settings = ModuleSelectorMeeting::findFirst();
        if ($settings === null) {
            $settings = new ModuleSelectorMeeting();
        }//*/
        $options = [];
        // $this->view->form = new ModuleSelectorMeetingForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/index");

        $this->view->rules = MeetingRules::find()->toArray();
    }

    /**
     * Modify page controller
     */
    public function modifyAction($id = null): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/$this->moduleUniqueID/module-modify-rule-meeting-index.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);

        $settings = MeetingRules::findFirst("id='$id'");
        if(!$settings){
            $settings = new MeetingRules();
            $settings->extension = Extensions::getNextFreeApplicationNumber();
        }
        $users  = Extensions::find(["type = 'SIP'", 'columns' => ['userid', 'callerid', 'number']]);
        $usersArray = [];
        foreach ($users as $user) {
            $usersArray[$user->userid] = $user->callerid . " ($user->number)";
        }
        $options = ['usersArray' => $usersArray];

        $usersIdArray = explode(',', $settings->usersId);
        $selectedUsers = [];
        foreach ($usersIdArray as $id){
            if(!isset($usersArray[$id])){
                continue;
            }
            $selectedUsers[] = [
                'id'   => $id,
                'name' => $usersArray[$id]
            ];
        }
        $this->view->selectedUsers = $selectedUsers;
        $this->view->usersId = $settings->usersId;
        $this->view->form = new MeetingRulesForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/modify");
        $this->view->users  = $users;
    }

    /**
     * Save settings AJAX action
     */
    public function saveAction() :void
    {
        $data       = $this->request->getPost();
        $record = ModuleSelectorMeeting::findFirst();
        if ($record === null) {
            $record = new ModuleSelectorMeeting();
        }
        $this->db->begin();
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'checkbox_field':
                case 'toggle_field':
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    if (array_key_exists($key, $data)) {
                        $record->$key = $data[$key];
                    } else {
                        $record->$key = '';
                    }
            }
        }

        if ($record->save() === FALSE) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->db->commit();
    }

    /**
     * Save settings AJAX action
     */
    public function saveRuleAction() :void
    {
        $data       = $this->request->getPost();
        $record = MeetingRules::findFirst("id='$data[id]'");
        if ($record === null) {
            $record = new MeetingRules();
        }
        $oldExten = $record->extension;

        $this->db->begin();
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'enabledAutoCall':
                case 'every1':
                case 'every2':
                case 'every3':
                case 'every4':
                case 'every5':
                case 'every6':
                case 'every7':
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    if (array_key_exists($key, $data)) {
                        $record->$key = trim($data[$key]);
                    } else {
                        $record->$key = '';
                    }
            }
        }

        if ($record->save() === FALSE) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        $filter = [
            'number=:number: AND type=:type:',
            'bind' => [
                'number' => $oldExten,
                'type' => 'MODULES',
            ]
        ];
        Extensions::find($filter)->delete();
        $filter = [
            'number=:number:',
            'bind' => [
                'number' => $record->extension,
            ]
        ];
        $data = Extensions::findFirst($filter);
        if (!$data){
            $data = new Extensions();
            $data->number            = $record->extension;
            $data->type              = 'MODULES';
            $data->callerid          = "$record->name <$record->extension>";
            $data->public_access     = 0;
            $data->show_in_phonebook = 0;
            $data->save();
        }else{
            $this->flash->error($this->translation->_('ex_ThisNumberIsNotFree'));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }

        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->view->id = $record->id;
        $this->db->commit();
    }

    public function deleteRuleAction()
    {
        $data = $this->request->get();
        $this->view->success = false;
        $filter = [
            "id=:id:",
            'bind' => [
                'id' => $data['id']
            ]
        ];
        $record = MeetingRules::findFirst($filter);
        if ($record) {
            $this->view->success = $record->delete();
        }
    }
}