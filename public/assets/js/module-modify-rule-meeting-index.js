"use strict";

/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */
var idUrl = 'module-selector-meeting';
var idForm = 'module-rule-meeting-form';
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
  validateRules: {
    at_time: {
      identifier: 'at_time',
      rules: [{
        type: 'regExp',
        value: /^(?:([01]?\d|2[0-3]):([0-5]?\d))(?:,\s*([01]?\d|2[0-3]):([0-5]?\d))*$/,
        prompt: globalTranslate.module_selectorMeeting_at_timePromt
      }]
    },
    extension: {
      identifier: 'extension',
      rules: [{
        type: 'number',
        prompt: globalTranslate.module_selectorMeeting_numberPromt
      }, {
        type: 'empty',
        prompt: globalTranslate.module_selectorMeeting_numberPromt
      }]
    },
    name: {
      identifier: 'name',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_selectorMeeting_namePromt
      }]
    }
  },

  /**
   * On page load we init some Semantic UI library
   */
  initialize: function initialize() {
    // инициализируем чекбоксы и выподающие менюшки
    window[className].$checkBoxes.checkbox();
    window[className].$dropDowns.dropdown();
    window[className].initializeForm();
    $('.menu .item').tab();
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
  cbAfterSendForm: function cbAfterSendForm(response) {
    if (response.success) {
      $('input[name="id"]').val(response.id);
      var newUrl = '/admin-cabinet/module-selector-meeting/modify/' + response.id;
      window.history.pushState({
        path: newUrl
      }, '', newUrl);
    }
  },

  /**
   * Initialize form parameters
   */
  initializeForm: function initializeForm() {
    Form.$formObj = window[className].$formObj;
    Form.url = "".concat(globalRootUrl).concat(idUrl, "/saveRule");
    Form.validateRules = window[className].validateRules;
    Form.cbBeforeSendForm = window[className].cbBeforeSendForm;
    Form.cbAfterSendForm = window[className].cbAfterSendForm;
    Form.initialize();
  }
};
$(document).ready(function () {
  window[className].initialize();
});
//# sourceMappingURL=module-modify-rule-meeting-index.js.map