<?php

/**
 * CrosswalkForm.inc.php
 *
 * Copyright (c) 2005-2006 The Public Knowledge Project
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package admin.form
 *
 * Form for site administrator to edit crosswalk settings.
 *
 * $Id$
 */

import('db.DBDataXMLParser');
import('form.Form');
import('field.Field'); // Bring in FIELD_TYPE_...

class CrosswalkForm extends Form {
	/** The ID of the crosswalk being edited */
	var $crosswalkId;

	/** The crosswalk object */
	var $crosswalk;

	/**
	 * Constructor.
	 * @param $crosswalkId omit for a new crosswalk
	 */
	function CrosswalkForm($crosswalkId = null) {
		parent::Form('admin/crosswalkForm.tpl');

		$this->crosswalkId = isset($crosswalkId) ? (int) $crosswalkId : null;
		
		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'name', 'required', 'admin.crosswalks.form.nameRequired'));
		$this->addCheck(new FormValidator($this, 'description', 'required', 'admin.crosswalks.form.descriptionRequired'));

		$this->harvesterPlugin = Request::getUserVar('harvesterPlugin');

		if ($crosswalkId) {
			$crosswalkDao =& DAORegistry::getDAO('CrosswalkDAO');
			$this->crosswalk =& $crosswalkDao->getCrosswalkById($this->crosswalkId);
		}

		HookRegistry::call('CrosswalkForm::CrosswalkForm', array(&$this));
	}
	
	/**
	 * Display the form.
	 */
	function display() {
		$schemaPlugins =& PluginRegistry::loadCategory('schemas');

		// Filter the list of schema plugins, if necessary.
		$schemaPluginName = Request::getUserVar('schemaPluginName');
		if (!empty($schemaPluginName) && isset($schemaPlugins[$schemaPluginName])) {
			$filteredPlugins = array($schemaPluginName => $schemaPlugins[$schemaPluginName]);
		} else {
			$filteredPlugins =& $schemaPlugins;
		}

		$templateMgr = &TemplateManager::getManager();
		$templateMgr->assign('crosswalkId', $this->crosswalkId);
		$templateMgr->assign('helpTopicId', 'site.siteManagement');
		$templateMgr->assign('schemaPluginName', $schemaPluginName);
		$templateMgr->assign('crosswalkTypes', array(
			FIELD_TYPE_STRING => 'admin.crosswalks.crosswalk.type.string',
			FIELD_TYPE_DATE => 'admin.crosswalks.crosswalk.type.date'
		));
		$templateMgr->assign_by_ref('schemaPlugins', $schemaPlugins);
		$templateMgr->assign_by_ref('filteredPlugins', $filteredPlugins);
		parent::display();
	}
	
	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		if (isset($this->crosswalk)) {
			$fields =& $this->crosswalk->getFields();
			$fields =& $fields->toArray();

			$this->_data = array(
				'name' => $this->crosswalk->getName(),
				'description' => $this->crosswalk->getDescription(),
				'fields' => &$fields,
				'crosswalkType' => $this->crosswalk->getType()
			);
		} else {
			$this->crosswalkId = null;
			$this->_data = array(
				'crosswalkType' => FIELD_TYPE_STRING
			);
		}

		// Allow user-submitted parameters to override the 
		// usual form values. This is useful for when users
		// change the harvester plugin so that they don't have
		// to re-key changes to form elements.
		$parameterNames = $this->getParameterNames();
		foreach ($parameterNames as $name) {
			$value = Request::getUserVar($name);
			if (!empty($value)) {
				$this->setData($name, $value);
			}
		}
	}

	function getParameterNames() {
		return array('name', 'description', 'crosswalkType');
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars($this->getParameterNames());
	}
	
	/**
	 * Save crosswalk settings.
	 */
	function execute() {
		$crosswalkDao = &DAORegistry::getDAO('CrosswalkDAO');
		
		if (!isset($this->crosswalk)) {
			$this->crosswalk = &new Crosswalk();
		}

		$this->crosswalk->setName($this->getData('name'));
		$this->crosswalk->setDescription($this->getData('description'));
		$this->crosswalk->setType($this->getData('crosswalkType'));

		if ($this->crosswalk->getCrosswalkId() != null) {
			$crosswalkDao->updateCrosswalk($this->crosswalk);
			$crosswalkId = $this->crosswalk->getCrosswalkId();
		} else {
			$this->crosswalk->setSeq(9999); // KLUDGE
			$crosswalkId = $crosswalkDao->insertCrosswalk($this->crosswalk);
			$crosswalkDao->resequenceCrosswalks();
		}

		$schemaPlugins =& PluginRegistry::loadCategory('schemas');
		$fieldDao =& DAORegistry::getDAO('FieldDAO');

		$oldFields =& $crosswalkDao->getFieldsByCrosswalkId($crosswalkId);
		$oldFields = $oldFields->toArray();

		// Save the fields selected for this crosswalk.
		$crosswalkDao->deleteCrosswalkFieldsByCrosswalkId($crosswalkId);
		foreach ($schemaPlugins as $schemaPluginName => $schemaPlugin) {
			foreach ($schemaPlugin->getFieldList() as $fieldName) {
				$isChecked = Request::getUserVar("$schemaPluginName-$fieldName");
				$isDisplayed = Request::getUserVar("$schemaPluginName-$fieldName-displayed");
				$fieldType = $schemaPlugin->getFieldType($fieldName);
				$isFieldMixedType = $schemaPlugin->isFieldMixedType($fieldName);

				$field =& $fieldDao->buildField($fieldName, $schemaPluginName);
				foreach ($oldFields as $oldField) {
					if (
						$oldField->getFieldId() == $field->getFieldId() &&
						!$isDisplayed &&
						($fieldType == $this->crosswalk->getType() || $isFieldMixedType)
					) {
						// This field was previously selected but wasn't displayed
						// on the page -- make sure it's maintained.
						$crosswalkDao->insertCrosswalkField($crosswalkId, $field->getFieldId());
					}
				}
				if ($isChecked && $isDisplayed && $fieldType == $this->crosswalk->getType()) {
					$crosswalkDao->insertCrosswalkField($crosswalkId, $field->getFieldId());
				}
			}
		}

		HookRegistry::call('CrosswalkForm::execute', array(&$this, &$this->crosswalk));
	}
	
}

?>
