<?php 

class ListFilterCompositeField extends CompositeField {
	/**
	 * @var ListFilterBase
	 */
	public $Filter = null;

	/** 
	 * @var string
	 */
	public $context = '';

	/** 
	 * @var array
	 */
	public $filterConfig = array();

	/**
	 * @var boolean
	 */
	protected $hasRendered = false;

	/**
	 * @config
	 * @var array
	 */
	private static $fieldset_tagnames = array(
		'legend' => 'fieldset',
		'nolegend' => 'div'
	);

	public function __construct(ListFilterBase $filter) {
		$this->Filter = $filter;

		// Inherit $Title and $Name variables
		$this->setTitle($filter->Title);
		$this->setName($filter->Name);

		// Select template to use based on class hierarchy
		$templateName = '';
		$templateHolderName = '';
		$classAncestry = array_reverse(ClassInfo::ancestry($this->Filter->class));
		foreach ($classAncestry as $subClass) {
			if ($templateName === '' && SSViewer::hasTemplate($subClass)) {
				$templateName = $subClass;
			}
			if ($templateHolderName === '' && SSViewer::hasTemplate($subClass.'_holder')) {
				$templateHolderName = $subClass.'_holder';
			}
		}
		$this->setTemplate($templateName);
		$this->setFieldHolderTemplate($templateHolderName);

		// Set children
		$filterFields = $filter->getFilterFieldsAll();
		if ($filterFields === null) {
			throw new Exception($filter->class.'::getFilterFieldsAll() should not return null.');
		}
		if ($filterFields) {
			$dataFields = $filterFields->dataFields();
			if ($dataFields) {
				foreach ($filterFields->dataFields() as $field) {
					$fieldName = $field->getName();
					$field->setName('FilterGroup_'.$filter->ID.'_'.$fieldName);
					$field->setAttribute('data-name', $fieldName);
					
				}
			}
		}
		parent::__construct($filterFields);
	}

	/**
	 * Get legend for a <fieldset>
	 *
	 * @return string
	 */
	public function getLegend() {
		return $this->Title();
	}

	/**
	 * Get the tag name to use for the <fieldset> wrapper.
	 *
	 * @return string
	 */
	public function getFieldsetTagName() {
		$fieldsetTagNames = $this->config()->fieldset_tagnames;
		if ($this->getLegend()) {
			return $fieldsetTagNames['legend'];
		}
		return $fieldsetTagNames['nolegend'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAttributes() {
		$result = parent::getAttributes();
		if ($this->context === 'FieldHolder') {
			unset($result['id']);
			$result['class'] = 'js-listfilter-filter';
			$result['data-fieldgroup-id'] = $this->Filter->ID;
			$result['data-fieldgroup-callback'] = $this->Filter->getJavascriptCallback();
			$filterConfig = $this->filterConfig;
			if ($filterConfig) {
				if (is_array($filterConfig)) {
					$filterConfig = json_encode($filterConfig);
				}
				$result['data-fieldgroup-config'] = $filterConfig;
			}
		}
		return $result;
	}

	/**
	 * Check if the filter has been rendered already or not
	 *
	 * @var boolean
	 */
	public function getHasRendered() {
		return $this->hasRendered;
	}

	/**
	 * {@inheritdoc}
	 */
	public function FieldHolder($properties = array()) {
		$this->hasRendered = true;

		$this->context = __FUNCTION__;
		$this->failover = $this->Filter;
		$result = parent::FieldHolder($properties);
		$this->failover = null;
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function Field($properties = array()) {
		$this->hasRendered = true;

		$this->context = __FUNCTION__;
		$this->failover = $this->Filter;
		$result = parent::Field($properties);
		$this->failover = null;
		return $result;
	}

	public function forTemplate() {
		$this->hasRendered = true;

		$this->failover = $this->Filter;
		$result = parent::forTemplate();
		$this->failover = null;
		return $result; 
	}
}