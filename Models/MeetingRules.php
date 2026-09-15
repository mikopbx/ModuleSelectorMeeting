<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2024 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleSelectorMeeting\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;

class MeetingRules extends ModulesModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Если 1 то правило активное и можно делать бекпы по расписанию
     *
     * @Column(type="integer", nullable=true)
     */
    public $enabledAutoCall;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every1;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every2;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every3;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every4;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every5;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every6;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $every7;

    /**
     * @Column(type="string", nullable=true)
     */
    public $at_time;

    /**
     * @Column(type="string", nullable=true)
     */
    public $extension;
    /**
     * @Column(type="string", nullable=true)
     */
    public $name;

    /**
     * @Column(type="string", nullable=true)
     */
    public $meetingLeader;

    /**
     * @Column(type="string", nullable=true)
     */
    public $usersId;

    public function initialize(): void
    {
        $this->setSource('m_MeetingRules');
        parent::initialize();
    }

}