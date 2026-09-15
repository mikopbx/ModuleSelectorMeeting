<form class="ui grey form" id="module-rule-meeting-form">
    {{ form.render('id') }}

    <div class="sixteen wide field disability">
        <label>{{ t._('module_selectorMeeting_name') }}</label>
        {{ form.render('name') }}
    </div>

    <div class="five wide field disability">
        <label>{{ t._('module_selectorMeeting_extension') }}</label>
        {{ form.render('extension') }}
    </div>

    <div class="sixteen wide field disability">
        <label>{{ t._('module_selectorMeeting_usersId') }}</label>
        <div id="usersId" class="ui multiple dropdown">
            <input type="hidden" name="usersId" value="{{ usersId }}">
            <i class="users icon"></i>
            {% for userData in selectedUsers %}
                <a class="ui label transition visible" data-value="{{ userData['id'] }}" style="display: inline-block !important;">
                    <i class="user icon"></i>{{ userData['name'] }} <i class="delete icon"></i>
                </a>
            {% endfor %}
            <span class="text">{{ t._('module_selectorMeeting_TitleOtherFilter') }}</span>
            <div class="menu">
                <div class="ui icon search input">
                    <i class="search icon"></i>
                    <input type="text" placeholder="{{ t._('module_selectorMeeting_UsersPlaceholder') }}" value="">
                </div>
                <div class="scrolling menu">
                    {% for user in users %}
                        <div class="item" data-value="{{ user['userid'] }}">
                            <i class="user icon"></i>
                            {{ user['callerid'] }} ({{ user['number'] }})
                        </div>
                    {% endfor %}
                </div>
            </div>
        </div>
    </div>

    <div class="ten wide field disability">
        <label>{{ t._('module_selectorMeeting_meetingLeader') }}</label>
        {{ form.render('meetingLeader') }}
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_enabledAutoCall') }}</label>
            {{ form.render('enabledAutoCall') }}
        </div>
    </div>

    <div class="field disability">
        <label>{{ t._('module_selectorMeeting_at_time') }}</label>
        {{ form.render('at_time') }}
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every1') }}</label>
            {{ form.render('every1') }}
        </div>
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every2') }}</label>
            {{ form.render('every2') }}
        </div>
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every3') }}</label>
            {{ form.render('every3') }}
        </div>
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every4') }}</label>
            {{ form.render('every4') }}
        </div>
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every5') }}</label>
            {{ form.render('every5') }}
        </div>
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every6') }}</label>
            {{ form.render('every6') }}
        </div>
    </div>

    <div class="field disability">
        <div class="ui checkbox">
            <label>{{ t._('module_selectorMeeting_every7') }}</label>
            {{ form.render('every7') }}
        </div>
    </div>

    {{ partial("partials/submitbutton", ['indexurl': 'module-selector-meeting/index']) }}
</form>