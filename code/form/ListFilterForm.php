<?php

class ListFilterFormValidator extends RequiredFields {
	// NOTE(Jake): Maybe make validator functionality perform on each 'FilterGroups()' list
	//             in the future if necessary.
}

class ListFilterForm extends Form {
	private static $allowed_actions = array(
		'doWidget',
	);

	private static $submit_use_button_tag = true;

	/**
	 * Default classes applied to the FormAction
	 */
	private static $submit_classes = 'button btn';

	/**
	 * Configure if the listing is loaded via AJAX or not.
	 *
	 * @var boolean
	 */
	private static $default_ajax_disabled = false;

	/**
	 * @var ListFilterSet
	 */
	protected $record = null;
	
	/**
	 * The current result set
	 *
	 * @var SS_List
	 */
	protected $resultList = null;

	/** 
	 * @var ListFilterWidget
	 */
	protected $widget = null;

	/**
	 * @var boolean
	 */
	protected $processedOnBeforeRender = false;

	/**
	 * @var boolean
	 */
	protected $ajax_enabled = false;

	/**
	 * @var string
	 */
	protected $formMethod = 'GET';

	public function __construct($controller, $name, ListFilterSet $listFilterSet = null) {
		$this->class = get_class(); 
		$this->controller = $controller;
		$this->record = $listFilterSet;
		if (!$this->record) {
			$pageOrDataRecord = $this->controller->data();
			if ($pageOrDataRecord && $pageOrDataRecord->hasExtension('ListFilterSetExtension')) {
				$this->record = $pageOrDataRecord->ListFilterSet();
				if (!$this->record || !$this->record->exists()) {
					throw new LogicException('No ListFilterSet configured on Page #'.$pageOrDataRecord->ID);
				}
			} else {
				throw new LogicException('Missing "ListFilterSetExtension" on '.$pageOrDataRecord->class. ' or failed to provide a ListFilterSet as the 3rd parameter.');
			}
		}
		if (!$this->record) {
			throw new LogicException('Missing "ListFilterSet". Unable to determine from controller and it was not passed as 3rd parameter.');
		}
		$this->record->setForm($this);

		$fieldNamespace = $this->class;

		$fields = $this->getFormFieldsAll();
		$actions = $this->getFormActionsAll();
		if (!$fields || $fields->count() == 0) {
			throw new LogicException('Missing fields on ListFilterForm. Do you have Filter Groups configured on List Filter Set #'.$this->record->ID.'?');
		}
		parent::__construct($controller, $name, $fields, $actions, ListFilterFormValidator::create());
		$this->disableSecurityToken();

		// Set attributes
		$this->addExtraClass('js-listfilter-form');
		if (Director::isDev()) {
			$this->setAttribute('data-debug', 1);
		}
		// NOTE(Jake): This is required to link the <form> and map <div> together (2016-08-23)
		$this->setAttribute('data-listfilter-id', $this->getRecord()->ID);
		$this->setAttribute('data-listfilter-backend', '{}');
		$this->setAttribute('data-ajax', (int)$this->getAJAXEnabled());

		// Retain selections
		$formData = $this->getVarData();
		$this->loadDataFrom($formData);

		// Workaround issue where loadDataFrom() doesn't handle CheckboxSetField.
		// (2016-10-14, Silverstripe 3.2)
		$dataFields = $this->Fields()->dataFields();
		if ($dataFields) {
			foreach ($dataFields as $fieldName => $dataField) {
				if ($dataField instanceof CheckboxSetField && isset($formData[$fieldName])) {
					$dataField->setValue(array_keys($formData[$fieldName]));
				}
			}
		}
	}
	
	/**
	 * Get the underlying result set this form has filtered down
	 *
	 * NOTE: This **MUST** be executed before rendering form fields so that finalizeFilter() on
	 *	     Solr fields, can update the facets after the Solr query. (Solr query contains the facets)
	 * 
	 * @return PaginatedList
	 */
	public function getResultList() {
		if ($this->resultList === null) {
			$this->resultList = $this->record->PaginatedFilteredList($this->getVarData(), $this);
		}
		return $this->resultList;
	}

	/**
	 * Opening tag for the <form>. Useful for splitting various fields across the page
	 * into different locations. (ie. Left sidebar has filtering options, right listing side has a sort dropdown)
	 *
	 * @return string
	 */
	public function TagStart() {
		$this->onBeforeRenderAll();

		$this->IncludeFormTag = false;
		return '<form '.$this->getAttributesHTML().'>';
	}

	/**
	 * Close form tag.
	 *
	 * @return string
	 */
	public function TagEnd() {
		return '</form>';
	}

	/** 
	 * Detect if the form has been submitted.
	 *
	 * @return boolean
	 */
	public function getHasSubmitted() {
		$actions = $this->Actions();
		if (!$actions) {
			return false;
		}
		$formData = $this->getVarData();
		$result = false;
		foreach ($actions as $action) {
			$result = $result || (isset($formData[$action->getName()]));
		}
		return $result;
	}

	/**
	 * @return ListFilterForm
	 */
	public function setAJAXEnabled($value) {
		$this->ajax_enabled = $value;
		$this->setAttribute('data-ajax', (int)$this->getAJAXEnabled());
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getAJAXEnabled() {
		$result = $this->ajax_enabled;
		if ($result === null) {
			return ($this->config()->default_ajax_disabled == false);
		}
		return $result;
	}

	/**
	 * @return $this
	 */
	public function setWidget(ListFilterWidget $widget) {
		$this->widget = $widget;
		if ($this->widget) {
			$this->widget->setForm($this);
		}
		return $this;
	}

	/**
	 * @return ListFilterWidget
	 */
	public function getWidget() {
		return $this->widget;
	}

	/**
	 * @return FieldList
	 */
	public function getFormFields() {
		$listFilterSet = $this->getRecord();
		$allGetVars = $this->getVarData();
		$getVarsForListFilters = $listFilterSet->unNamespaceFilterFields($allGetVars);

		$fields = new FieldList();
		foreach ($listFilterSet->ListFiltersPersist() as $filterGroup) {
			$getVars = array();
			if (isset($getVarsForListFilters[$filterGroup->ID])) {
				$getVars = $getVarsForListFilters[$filterGroup->ID];
			}
			$filterGroup->init($getVars);
			$compositeField = ListFilterCompositeField::create($filterGroup);
			$compositeField->filterConfig = $filterGroup->getFilterConfig($getVars);
			$fields->push($compositeField);
		}
		return $fields;
	}

	/**
	 * @return FieldList
	 */
	public function getFormActions() {
		$actions = new FieldList(
			$submitButton = FormAction::create('doGetListing', 'Search')
				->setUseButtonTag($this->config()->submit_use_button_tag)
				->addExtraClass($this->config()->submit_classes)
		);
		return $actions;
	}

	/**
	 * Get the form fields for the form on this page. Can modify this FieldSet
	 * by using {@link updateFormFields()} on an {@link Extension} subclass which
	 * is applied to this form.
	 *
	 * @return FieldList
	 */
	final public function getFormFieldsAll() {
		$fields = $this->getFormFields();
		$this->extend('updateFormFields', $fields);
		return $fields;
	}

	/**
	 * @return FieldList
	 */
	final public function getFormActionsAll() {
		$actions = $this->getFormActions();
		$this->extend('updateFormActions', $actions);
		return $actions;
	}

	/**
	 * {@inheritdoc}
	 */
	public function loadDataFrom($data, $mergeStrategy = 0, $fieldList = null) {
		$result = parent::loadDataFrom($data, $mergeStrategy, $fieldList);

		$dataFields = $this->Fields()->dataFields();
		foreach($dataFields as $fieldName => $dataField) {
			if ($dataField instanceof CheckboxSetField) {
				// Stop bad validation when a facet/checkbox option is removed dynamically
				// in 'ListFilterBase::finalizeFilter()'. (ie. Solr result set returns new options that dont
				// exist, but the value is still set)
				$value = $dataField->Value();
				if ($value && is_array($value)) {
					$value = array_intersect($value, array_keys($dataField->getSourceAsArray()));
					$dataField->setValue($value);
				}
			}
		}
		return $result;
	}

	/**
	 * Get a list of filter group fields by their class.
	 *
	 * @return FieldList
	 */
	public function fieldsByClass($class) {
		if (!$class) {
			return false;
		}
		if (isset($class[0]) && $class[0] === '*') {
			// Ignore subclassing, must be the exact class
			$class = substr($class, 1);
			$result = new FieldList();
			foreach ($this->fields as $field) {
				if ($field->Filter->class === $class) {
					$result->push($field);
				}
			}
			return $result;
		}
		$result = new FieldList();
		foreach ($this->fields as $field) {
			if ($field->Filter instanceof $class) {
				$result->push($field);
			}
		}
		return $result;
	}

	/**
	 * If visiting the page with GET parameterss.
	 *
	 * @return array
	 */
	public function getVarData() {
		$data = array();
		if ($this->request) {
			$data = $this->request->getVars();
		}
		if (!$data) {
			// Fallback to controller
			$data = $this->controller->getRequest()->getVars();
		}
		return $data;
	}

	/**
	 * @return ListFilterSet
	 */
	public function getRecord() {
		return $this->record;
	}

	/**
	 * Get current page.
	 *
	 * @return SiteTree
	 */
	public function getPage() {
		$controller = $this->getController();
		if (!$controller->hasMethod('data')) {
			return null;
		}
		return $controller->data();
	}

	/**
	 * Process the filter backend data before rendering the form.
	 */
	public function processFilterBackendData() {
		// todo(Jake): ensure this doesn't affect AJAX performance unnecessarily.
		//			   ie. doGetListing_Ajax

		// Process widget (if set)
		$backendFilterGroupData = $this->FilterBackendData();
		if ($backendFilterGroupData && is_array($backendFilterGroupData)) {
			$this->setAttribute('data-listfilter-backend', json_encode($backendFilterGroupData));
		}
		//$this->processedFilterBackendData = true;
	}

	/**
	 * @return array
	 */
	public function getTemplates($templateName, $recordOrClasses = null) {
		if ($recordOrClasses === null) {
			$recordOrClasses = $this->getPage();
		}
		$result = ListFilterUtility::get_templates($templateName, $recordOrClasses);
		// todo(Jake): Add and test
		// $this->extend('updateTemplates', $result, $templateName, $recordOrClasses);
		return $result;
	}

	/**
	 * For calling $ListFilterForm.ShowingMessage in a template.
	 *
	 * @return HTMLText
	 */
	public function ShowingMessage(SS_List $list = null) {
		if ($list === null) {
			$list = $this->getResultList();
		}

		$data = array();
		if ($list instanceof PaginatedList) {
			$start = 0;
			$request = $list->getRequest();
			if ($request) {
				$getVarName = $list->getPaginationGetVar();
				if($request && isset($request[$getVarName]) && $request[$getVarName] > 0) {
					$start = (int)$request[$getVarName];
				}
			}

			$data['TotalCount'] = $list->TotalItems();
			if ($start < $data['TotalCount']) {
				$data['OffsetStart'] = $start + 1;
				$data['ThisPage'] = $list->CurrentPage();
				$data['TotalPages'] = $list->TotalPages();
				$data['OffsetEnd'] = $start + $list->getPageLength();
				if ($data['OffsetEnd'] === 0) {
					$data['OffsetEnd'] = $data['OffsetStart'];
				}

				if ($data['OffsetEnd'] > $data['TotalCount']) {
					$data['OffsetEnd'] = $data['TotalCount'];
				} 
				$data['Count'] = $data['OffsetEnd'] - $data['OffsetStart'];
			} else {
				$data['TotalCount'] = 0;
			}
		} else {
			$data['TotalCount'] = $list->count();
			$data['Count'] = $data['TotalCount'];
			$data['ThisPage'] = 1;
			$data['TotalPages'] = 1;
			$data['OffsetStart'] = 1;
			$data['OffsetEnd'] = $data['TotalCount'];
		}

		$controller = $this->getController();
		$result = $controller->customise($data);
		$result = $result->renderWith($this->getTemplates('ListFilterShowingMessage', $this->getPage()));
		return $result;
	}

	/**
	 * For calling $ListFilterForm.Listing in a template.
	 *
	 * @return HTMLText
	 */
	public function Listing(SS_List $list = null) {
		if ($list === null) {
			$list = $this->getResultList();
		}
		$result = $this->getController()->customise(array(
			'Results' => $list,
		));
		$result = $result->renderWith($this->getTemplates('ListFilterListing', $this->getPage()));
		return $result;
	}

	/**
	 * @return array
	 */
	public function FilterBackendData($data = null) {
		$widget = $this->getWidget();
		if (!$widget) {
			// Don't send back backend data if there is no widget configured
			// to use it.
			return;
		}
		if ($data === null) {
			$data = $this->getVarData();
		}
		$listFilterSet = $this->getRecord();
		$filterGroupData = $listFilterSet->FilterBackendData($data, $widget);
		return $filterGroupData;
	}

	/**
	 * For calling $ListFilterForm.Widget in a template.
	 *
	 * @return HTMLText
	 */
	public function doWidget($request = null) {
		$widget = $this->getWidget();
		if (!$widget) {
			throw new Exception('Must configure a widget with "'.__CLASS__.'::setWidget()" in your '.$this->getName().'() function on your '.$this->getController()->class.' class.');
		}
		return $widget;
	}

	/** 
	 * @return string
	 */
	public function Link($action = null) {
		return Controller::join_links($this->controller->Link(), $this->name, $action);
	}

	/** 
	 * @return HTMLText|string
	 */
	public function doGetListing($data) {
		if (Director::is_ajax()) {
			if (!$this->getAJAXEnabled()) {
				$exception = new SS_HTTPResponse_Exception('Invalid operation.', 500);
				$exception->getResponse()->addHeader('X-Status', $exception->getMessage());
				throw $exception;
			}
			$result = $this->doGetListing_Ajax($data);
			if (is_array($result)) {
				// Convert HTMLText/etc, into 'forTemplate'
				foreach ($result as $k => $v) {
					if ($v && $v instanceof HTMLText) {
						$result[$k] = $v->forTemplate();
					}
				}
				$result = json_encode($result);
				$this->controller->getResponse()->addHeader('Content-Type', 'application/json');
				return $result;
			} else {
				return $result;
			}
		}
		return $this->doGetListing_Static($data);
	}

	/** 
	 * @return array
	 */
	public function doGetListing_Ajax($data) {
		$result = array(
			'Listing' => $this->Listing(),
			'ShowingMessage' => $this->ShowingMessage(),
		);
		// Get filtering information if $Widget is set.
		$filterGroupData = $this->FilterBackendData($data);
		if ($filterGroupData !== null) {
			$result['FilterGroups'] = $filterGroupData;
		}
		if (count($result) === 1) {
			// Return raw string/template 
			return reset($result);
		}
		return $result;
	}

	/**
	 * @return null
	 */
	public function doGetListing_Static($data) {
		$queryVars = '';
		$getVars = $data;
		unset($getVars['url']);
		unset($getVars['_method']);
		foreach ($getVars as $field => $value) {
			if (!$value) {
				unset($getVars[$field]);
			}
		}
		if ($getVars) { 
			$queryVars = '?'.http_build_query($getVars);
		}
		return $this->controller->redirect($this->controller->Link().$queryVars);
	}

	/**
	 * Add CSS/JavaScript requirements for form.
	 *
	 * @return void
	 */
	public function addRequirements() {
		Requirements::javascript(ListFilterUtility::MODULE_DIR.'/javascript/ListFilter.js');
		Requirements::javascript(ListFilterUtility::MODULE_DIR.'/javascript/ListFilterForm.js');
	}

	/**
	 * @return void
	 */
	public function onBeforeRender() {
	}

	/**
	 * @return void
	 */
	final public function onBeforeRenderAll() {
		if ($this->processedOnBeforeRender) {
			return;
		}
		$this->processFilterBackendData();
		// Execute list at this point, this allows 'finaliseFilter' to modify any form fields when
		// necessary (ie. ListFilterSolrFacet)
		$this->getResultList();
		
		$this->onBeforeRender();
		$this->extend('onBeforeRender', $this);

		$this->processedOnBeforeRender = true;
	}


	public function forTemplate() {
		$this->addRequirements();
		$this->onBeforeRenderAll();

		// Failover to current page
		// todo(Jake): Perhaps change this to $this->getPage()
		$this->failover = $this->getRecord();
		$result = parent::forTemplate();
		$this->failover = null;
		return $result;
	}
}