<?php

class ListFilterFormValidator extends RequiredFields {
	// NOTE(Jake): Maybe make validator functionality perform on each 'FilterGroups()' list
	//			   in the future if necessary.
}

class ListFilterForm extends Form {
	private static $allowed_actions = array(
		'Widget',
	);

	private static $submit_use_button_tag = true;

	private static $submit_classes = 'button btn';

	/**
	 * @var ListFilterSet
	 */
	protected $record = null;

	/** 
	 * @var ListFilterWidget
	 */
	protected $widget = null;

	protected $formMethod = 'GET';

	public function __construct($controller, $name, ListFilterSet $listFilterSet = null) {
		$this->class = get_class(); 
		$this->controller = $controller;
		$this->record = $listFilterSet;
		if (!$this->record) {
			$pageOrDataRecord = $this->controller->data();
			if ($pageOrDataRecord && $pageOrDataRecord->hasExtension('ListFilterSetExtension')) {
				$this->record = $pageOrDataRecord->ListFilterSet();
			} else {
				throw new Exception('Missing "ListFilterSetExtension" on '.$page->class. ' or failed to provide a ListFilterSet as the 3rd parameter.');
			}
		}
		if (!$this->record) {
			throw new Exception('Missing "ListFilterSet". Unable to determine from controller and it was not passed as 3rd parameter.');
		}

		$fieldNamespace = $this->class;

		$fields = $this->getFormFieldsAll();
		$actions = $this->getFormActionsAll();
		parent::__construct($controller, $name, $fields, $actions, ListFilterFormValidator::create());
		$this->disableSecurityToken();

		// Set attributes
		$this->addExtraClass('js-listfilter-form');
		if (Director::isDev()) {
			$this->setAttribute('data-debug', 1);
		}
		// NOTE(Jake): This is required to link the <form> and map <div> together (2016-08-23)
		$this->setAttribute('data-listfilter-id', $this->getRecord()->ID);
		$backendFilterGroupData = $this->FilterBackendData();
		if ($backendFilterGroupData && is_array($backendFilterGroupData)) {
			$this->setAttribute('data-listfilter-backend', json_encode($backendFilterGroupData));
		} else {
			// Empty JS object/map
			$this->setAttribute('data-listfilter-backend', '{}');
		}

		// Retain selections
		$formData = $this->getVarData();
		$this->loadDataFrom($formData);

		// Workaround issue where loadDataFrom() doesn't handle CheckboxSetField.
		$dataFields = $this->Fields()->dataFields();
		foreach ($dataFields as $fieldName => $dataField) {
			if ($dataField instanceof CheckboxSetField && isset($formData[$fieldName])) {
				$dataField->setValue(array_keys($formData[$fieldName]));
			}
		}
	}

	/**
	 * Opening tag for the <form>. Useful for splitting various fields across the page
	 * into different locations. (ie. Left sidebar has filtering options, right listing side has a sort dropdown)
	 *
	 * @return string
	 */
	public function TagStart() {
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
	 * For calling $ListFilterForm.Listing in a template.
	 *
	 * @return HTMLText
	 */
	public function Listing(SS_List $list = null) {
		if ($list === null) {
			$list = $this->getRecord()->PaginatedFilteredList($this->getVarData(), $this);
		}
		// todo(Jake): get class ancestry for rendering *_ListFilterListing
		$result = $this->customise(array(
			'Results' => $list,
		))->renderWith(array($list->dataClass().'_ListFilterListing', 'ListFilterListing'));
		$result->Items = $list;
		$result->Count = $list->getIterator()->count();
		if ($list instanceof PaginatedList) {
			$result->TotalItems = $list->count();
		} else {
			$result->TotalItems = $result->Count;
		}
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
	public function Widget($request = null) {
		$widget = $this->getWidget();
		if (!$widget) {
			throw new Exception('Must configure a widget with "setWidget()"');
		}
		$widget->setForm($this);
		return $widget;
	}

	/** 
	 * @return string
	 */
	public function Link($action = null) {
		return Controller::join_links(Director::absoluteBaseURL(), $this->controller->Link(), $this->name, $action);
	}

	/** 
	 * @return HTMLText|string
	 */
	public function doGetListing($data) {
		if (Director::is_ajax()) {
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
		$list = $this->getRecord()->PaginatedFilteredList($data, $this);
		$template = $this->Listing($list);
		$result = array();
		$filterGroupData = $this->FilterBackendData($data);
		if ($filterGroupData !== null) {
			$result['FilterGroups'] = $filterGroupData;
		}
		$count = 0;
		foreach ($list as $r) {
			++$count;
		}
		// NOTE(Jake): getIterator() ensures PaginatedList only returns the ->limit() amount in pagination
		$result['Count'] = $list->getIterator()->count();
		if ($list instanceof PaginatedList) {
			$result['TotalItems'] = $list->getTotalItems();
		} else {
			$result['TotalItems'] = $result['Count'];
		}
		$result['Template'] = $template;
		if (count($result) === 1) {
			// Return raw string/template if 
			return reset($result);
		} else {
			return $result;
		}
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
			if (!$value || substr($field,0,7) === 'action_') {
				unset($getVars[$field]);
			}
		}
		if ($getVars) { 
			$queryVars = '?'.http_build_query($getVars);
		}
		return $this->controller->redirect($this->controller->Link().$queryVars);
	}

	public function onBeforeRender() {
		Requirements::javascript(ListFilterUtility::MODULE_DIR.'/javascript/ListFilter.js');
		Requirements::javascript(ListFilterUtility::MODULE_DIR.'/javascript/ListFilterForm.js');
	}

	public function forTemplate() {
		$this->onBeforeRender();
		$this->extend('onBeforeRender', $this);
		// Failover to $record but only in template context.
		$this->failover = $this->getRecord();
		$result = parent::forTemplate();
		$this->failover = null;
		return $result;
	}
}