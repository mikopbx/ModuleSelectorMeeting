/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */
const idUrl     = 'module-selector-meeting';
const idForm    = 'module-selector-meeting-form';
const className = 'ModuleSelectorMeeting';
const inputClassName = 'mikopbx-module-input';

/* global globalRootUrl, globalTranslate, Form, Config */
const ModuleSelectorMeeting = {
	$formObj: $('#'+idForm),
	$checkBoxes: $('.ui.checkbox'),
	$dropDowns: $('.ui.dropdown'),
	/**
	 * Field validation rules
	 * https://semantic-ui.com/behaviors/form.html
	 */
	validateRules: {
	},
	/**
	 * On page load we init some Semantic UI library
	 */
	initialize() {
		// инициализируем чекбоксы и выподающие менюшки
		window[className].$checkBoxes.checkbox();
		window[className].$dropDowns.dropdown();
		window[className].initializeForm();
		$('.menu .item').tab();
		$('#button-meeting-add').on('click', ModuleSelectorMeeting.addMeeting);
		$(document).on('click', '#meeting-table a.delete', ModuleSelectorMeeting.deleteMeeting);

	},

	deleteMeeting(e){
		e.preventDefault();
		let linkElement = $(this);
		$.ajax({
			url: linkElement.attr('href'),
			type: 'GET',
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					linkElement.closest('tr').remove();
				}
			},
			error: function(xhr, status, error) {
				console.error(error);
			}
		});
	},
	addMeeting(){
		window.location.href = `/admin-cabinet/module-selector-meeting/modify/`;
	},
	/**
	 * We can modify some data before form send
	 * @param settings
	 * @returns {*}
	 */
	cbBeforeSendForm(settings) {
		const result = settings;
		result.data = window[className].$formObj.form('get values');
		return result;
	},
	/**
	 * Some actions after forms send
	 */
	cbAfterSendForm() {
		// window[className].applyConfigurationChanges();
	},
	/**
	 * Initialize form parameters
	 */
	initializeForm() {
		Form.$formObj = window[className].$formObj;
		Form.url = `${globalRootUrl}${idUrl}/save`;
		Form.validateRules = window[className].validateRules;
		Form.cbBeforeSendForm = window[className].cbBeforeSendForm;
		Form.cbAfterSendForm = window[className].cbAfterSendForm;
		Form.initialize();
	},
};

$(document).ready(() => {
	window[className].initialize();
});

