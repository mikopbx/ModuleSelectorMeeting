<div class="ui" data-tab="rules">
    <button class="ui primary button" id="button-meeting-add">{{ t._('module_selectorMeeting_AddMiiting') }} </button>
      <br>
      <table id="meeting-table" data-report-name="polling" class="ui small very compact single line unstackable celled striped table ">
       <thead>
       <tr>
           <th class="one wide">{{ t._('module_selectorMeeting_extension') }}</th>
           <th class="">{{ t._('module_selectorMeeting_name') }}</th>
           <th class="one wide"></th>
       </tr>
       </thead>
       <tbody>
       {% for rule in rules %}
       <tr data-exten-id="{{rule['id']}}">
           <th class="one wide">{{ rule['extension'] }}</th>
           <th class="">{{ rule['name'] }}</th>
           <th class="one wide">
               <div class="ui basic icon buttons action-buttons tiny">
                 <a href="/admin-cabinet/module-selector-meeting/modify/{{rule['id']}}" class="ui button edit popuped" data-content=""><i class="icon edit blue"></i> </a>
                 <a href="/admin-cabinet/module-selector-meeting/deleteRule?id={{rule['id']}}" class="ui button delete two-steps-delete popuped" data-content=""><i class="icon trash red"></i> </a>
               </div>
           </th>
       </tr>
       {% else %}
       <tr>
           <td colspan="3" class="dataTables_empty">{{ t._('dt_TableIsEmpty') }}</td>
       </tr>
       {% endfor %}

       </tbody>
   </table>
</div>