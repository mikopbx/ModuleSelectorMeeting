"use strict";

/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */
var idUrl = 'module-selector-meeting';
var idForm = 'module-selector-meeting-form';
var className = 'ModuleSelectorMeeting';
var inputClassName = 'mikopbx-module-input';
/* global globalRootUrl, globalTranslate, Form, Config */

var ModuleSelectorMeeting = {
  $formObj: $('#' + idForm),
  $checkBoxes: $('.ui.checkbox'),
  $dropDowns: $('.ui.dropdown'),

  /**
   * Field validation rules
   * https://semantic-ui.com/behaviors/form.html
   */
  validateRules: {},

  /**
   * On page load we init some Semantic UI library
   */
  initialize: function initialize() {
    // инициализируем чекбоксы и выподающие менюшки
    window[className].$checkBoxes.checkbox();
    window[className].$dropDowns.dropdown();
    window[className].initializeForm();
    $('.menu .item').tab();
    $('#button-meeting-add').on('click', ModuleSelectorMeeting.addMeeting);
    $(document).on('click', '#meeting-table a.delete', ModuleSelectorMeeting.deleteMeeting);
  },
  deleteMeeting: function deleteMeeting(e) {
    e.preventDefault();
    var linkElement = $(this);
    $.ajax({
      url: linkElement.attr('href'),
      type: 'GET',
      dataType: 'json',
      success: function success(response) {
        if (response.success) {
          linkElement.closest('tr').remove();
        }
      },
      error: function error(xhr, status, _error) {
        console.error(_error);
      }
    });
  },
  addMeeting: function addMeeting() {
    window.location.href = "/admin-cabinet/module-selector-meeting/modify/";
  },

  /**
   * We can modify some data before form send
   * @param settings
   * @returns {*}
   */
  cbBeforeSendForm: function cbBeforeSendForm(settings) {
    var result = settings;
    result.data = window[className].$formObj.form('get values');
    return result;
  },

  /**
   * Some actions after forms send
   */
  cbAfterSendForm: function cbAfterSendForm() {// window[className].applyConfigurationChanges();
  },

  /**
   * Initialize form parameters
   */
  initializeForm: function initializeForm() {
    Form.$formObj = window[className].$formObj;
    Form.url = "".concat(globalRootUrl).concat(idUrl, "/save");
    Form.validateRules = window[className].validateRules;
    Form.cbBeforeSendForm = window[className].cbBeforeSendForm;
    Form.cbAfterSendForm = window[className].cbAfterSendForm;
    Form.initialize();
  }
};
$(document).ready(function () {
  window[className].initialize();
});
//# sourceMappingURL=module-selector-meeting-index.js.map