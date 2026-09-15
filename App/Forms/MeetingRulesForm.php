<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 9 2018
 *
 */
namespace Modules\ModuleSelectorMeeting\App\Forms;

use MikoPBX\Common\Models\Extensions;
use Phalcon\Forms\Form;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\Numeric;
use Phalcon\Forms\Element\Password;
use Phalcon\Forms\Element\Check;
use Phalcon\Forms\Element\TextArea;
use Phalcon\Forms\Element\Hidden;
use Phalcon\Forms\Element\Select;


class MeetingRulesForm extends ModuleBaseForm
{

    public function initialize($entity = null, $options = null) :void
    {
        $this->add(new Hidden('id', ['value' => $entity->id]));
        $this->add(new Text('name', ['value' => $entity->name]));
        $this->add(new Text('at_time', ['value' => $entity->at_time]));
        $this->add(new Text('extension', ['value' => $entity->extension]));

        $this->addCheckBox('enabledAutoCall', intval($entity->enabledAutoCall) === 1);
        for ($i = 1; $i <= 7; $i++) {
            $fieldName = "every$i";
            $this->addCheckBox($fieldName, intval($entity->$fieldName) === 1);
        }

        $providers = new Select('dropdown_field', $options['providers'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => false,
            'class'    => 'ui selection dropdown provider-select',
        ]);
        $this->add($providers);

        $meetingLeader = new Select('meetingLeader', $options['usersArray'], [
            'using'    => [
                'id',
                'name',
            ],
            'value'    => $entity->meetingLeader,
            'useEmpty' => true,
            'class'    => 'ui selection search dropdown',
        ]);
        $this->add($meetingLeader);

        $usersId = new Select('usersId', $options['usersArray'], [
            'using'    => [
                'id',
                'name',
            ],
            'value'    => explode(',', $entity->usersId),
            'useEmpty' => true,
            'class'    => 'ui multiple selection search dropdown',
        ]);
        $this->add($usersId);
    }
}