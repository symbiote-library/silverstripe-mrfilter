<?php

abstract class ListFilterWidget extends Controller {
	private static $hide_ancestor = 'ListFilterWidget';

	/** 
	 * @var ListFilterForm
	 */
	protected $form;

	/**
	 * @var DataObject
	 */
	protected $record = null;

	/**
	 * Extra CSS classes
	 *
	 * @var array
	 */
	protected $extraClasses;

	public function __construct() {
		parent::__construct();
		$this->addExtraClass('js-listfilter-widget');
	}

	/** 
	 * @return void
	 */
	public function onBeforeRender() {
	}

	/**
	 * @return ListFilterSet
	 */
	public function getListFilterSet() {
		$form = $this->getForm();
		return ($form) ? $form->getRecord() : null;
	}

	/**
	 * @return DataObject
	 */
	public function getRecord() {
		return $this->record;
	}

	/**
	 * @return ListFilterWidget
	 */
	public function setRecord(DataObjectInterface $record) {
		$this->record = $record;
		return $this;
	}

	/** 
	 * @return ListFilterWidget
	 */
	public function setForm(ListFilterForm $form) {
		$this->form = $form;
		return $this;
	}

	/** 
	 * @return ListFilterForm
	 */
	public function getForm() {
		return $this->form;
	}
	
	/**
	 * @return array
	 */
	public function getDataAttributes() {
		$attributes = array();
		$listFilterSet = $this->getListFilterSet();
		if ($listFilterSet) {
			// NOTE(Jake): This is required to link the <form> and map <div> together (2016-08-23)
			$attributes['listfilter-id'] = $listFilterSet->ID;
		}
		return $attributes;
	}

	/**
	 * @return array
	 */
	final public function getDataAttributesAll() {
		$attributes = $this->getDataAttributes();
		$this->extend('updateDataAttributes', $attributes);
		return $attributes;
	}

	/**
	 * @return string
	 */
	public function DataAttributesHTML() {
		$result = '';
		$attributes = $this->getDataAttributesAll();
		foreach ($attributes as $attribute => $value) {
			if ($value !== null) {
				if (is_array($value)) {
					$value = Convert::raw2att(json_encode($value));
				}
				$result .= 'data-'.$attribute.'="'.$value.'" ';
			}
		}
		return rtrim($result);
	}

	/**
	 * If visiting the page with GET parameterss.
	 *
	 * @return array
	 */
	public function getVarData() {
		$data = $this->getRequest()->getVars();
		if (!$data) {
			// Fallback to form
			$data = $this->getForm()->getVarData();
		}
		return $data;
	}

	/** 
	 * @return string
	 */
	public function Link($action = null) {
		return Controller::join_links($this->getForm()->Link('Widget'), $action);
	}

	/**
	 * Compiles all CSS-classes set on this.
     *
	 * @return string
	 */
	public function extraClass() {
		$classes = array();
		if($this->extraClasses) {
			$classes = array_merge(
				$classes,
				array_values($this->extraClasses)
			);
		}
		return implode(' ', $classes);
	}

	/**
	 * Add one or more CSS-classes
	 *
	 * Multiple class names should be space delimited.
	 *
	 * @param string $class
	 *
	 * @return $this
	 */
	public function addExtraClass($class) {
		$classes = preg_split('/\s+/', $class);
		foreach ($classes as $class) {
			$this->extraClasses[$class] = $class;
		}
		return $this;
	}

	/**
	 * Remove one or more CSS-classes
	 *
	 * @param string $class
	 *
	 * @return $this
	 */
	public function removeExtraClass($class) {
		$classes = preg_split('/\s+/', $class);
		foreach ($classes as $class) {
			unset($this->extraClasses[$class]);
		}
		return $this;
	}

	/** 
	 * @return HTMLText
	 */
	public function forTemplate() {
		if (!$this->getForm()) {
			throw new Exception('Missing $form on '.$this->class.'. Calling syntax in a template should be "$ListFilterForm.Widget" not "$ListFilterWidget".');
		}
		$this->onBeforeRender();
		$this->extend('onBeforeRender', $this);
		// Failover to $form but only in template context.
		$this->failover = $this->getForm();
		$result = $this->renderWith(array($this->class, __CLASS__));
		$this->failover = null;
		return $result;
	}
}