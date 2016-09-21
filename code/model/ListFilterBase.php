<?php

class ListFilterBase extends DataObject {
	private static $db = array(
		'Title' => 'Varchar(255)',
		'Sort'	=> 'Int',
	);

	private static $has_one = array(
		'Parent' => 'ListFilterSet',
	);

	private static $summary_fields = array(
		'singular_name' => 'Type',
		'Title' => 'Title',
		'ContextSummaryField' => 'Context',
	);

	private static $default_sort = 'Sort';

	/**
	 * Hide from 'GridFieldAddClassesButton'
	 *
	 * @var string
	 */
	private static $hide_ancestor = 'ListFilterBase';

	/**
	 * @var array
	 */
	protected static $shared_filter_instances = array();

	/** 
	 * This is executed before 'getFilterFields' and allows you to set properties
	 * based on the $_GET vars so that templates can utilize the logic.
	 *
	 * ie. The use case for this is to detect if the user has explicitly disabled
	 * 	   finding records relevant to their location, but only for that request.
	 *
	 * @return null
	 */
	public function init(array $data) {
	}

	/** 
	 * @return FieldList
	 */
	public function getCMSFields() {
		$self = &$this;
		$self->beforeUpdateCMSFields(function($fields) use ($self) {
			$fields->removeByName(array('Sort', 'ParentID'));
			$error = $self->getContextSummaryField(true);
			if ($error) {
				$fields->insertBefore(LiteralField::create('ConfigError_Literal', $self->getContextSummaryField(true)), 'Title');
			}
		});
		$fields = parent::getCMSFields();
		return $fields;
	}

	/** 
	 * Get list class name
	 * ie. 'SiteTree'
	 *
	 * @return string
	 */
	public function getListClass() {
		return $this->Parent()->ListClass;
	}

	/** 
	 * The fields to show on the frontend to manipulate the map and
	 * listing content.
	 *
	 * @return FieldList
	 */
	public function getFilterFields() {
		return new FieldList();
	}

	/** 
	 * @return FieldList
	 */
	final public function getFilterFieldsAll() {
		$fields = $this->getFilterFields();
		$this->extend('updateFilterFields', $fields);
		return $fields;
	}

	/**
	 * Get config variables to utilize with the JavaScript callback.
	 * ie. $(this).data('data-fieldgroup-config')
	 *
	 * @return array
	 */
	public function getFilterConfig(array $data) {
		return array();
	}

	/**
	 * When generating map pins / widget data, this function will
	 * add additional data so that the pins can be filtered in JavaScript.
	 *
	 * @return mixed
	 */
	public function getFilterData(DataObject $record) {
		return array(
			'value' => null
		);
	}

	/**
	 * The data to return for filtering based on the backend request.
	 * ie. For Solr, you just want to return a map/associate array of IDs that matches the search
	 *     for the frontend filtering.
	 *
	 * @return string
	 */
	public function getFilterBackendData(SS_List $list, array $data) {
		return null;
	}

	/**
	 * When the form hits the backend (for updating the listing), filter
	 * the SS_List by the filter fields.
	 *
	 * @return SS_List|ListFilterShared|null
	 */
	public function applyFilter(SS_List $list, array $data) {
		throw new Exception('Missing "'.__FUNCTION__.'" implementation for "'.$this->class.'"');
	}
    
    /**
     * Allow inspection of SS_List and shared filters to change current objects state.
     * ie. ListFilterSolrFacet gets facets from the 
     *
     * @param SS_List $list
     */
    public function finaliseFilter(SS_List $list) {
    }

	/**
	 * Return a ListFilterShared object to share between ListFilterBase objects.
	 *
	 * @return ListFilterShared
	 */
	public function SharedFilter($class, $namespace = '') {
		if (isset(self::$shared_filter_instances[$namespace][$class])) {
			return self::$shared_filter_instances[$namespace][$class];
		}
		$sharedFilter = $this->LocalFilter($class);
		self::$shared_filter_instances[$namespace][$class] = $sharedFilter;
		return $sharedFilter;
	}

	/**
	 * Return a ListFilterShared object to use locally only.
	 *
	 * @return ListFilterShared
	 */
	public function LocalFilter($class) {
		$sharedFilter = $class::create($this->Parent());
		return $sharedFilter;
	}

	/**
	 * The name of the event to pass to 
	 * jQuery('.js-listfilter-filter').triggerHandler()
	 *
	 * For examples, check 'MapWidgetFilterGroup.js'
	 *
	 * @return string
	 */
	public function getJavascriptCallback() {
		return $this->class;
	}

	/**
	 * {@inheritdoc}
	 */
	public function singular_name() {
		if ($this->class === __CLASS__) {
			return parent::singular_name();
		}
		$singularName = parent::singular_name();
		return str_replace('List Filter', '', $singularName);
	}

	/**
	 * Get the calling object of the filtering functions like 'applyFilter'.
	 *
	 * @return object
	 */
	public function getCaller() {
		return $this->Parent()->getCaller();
	}

	/**
	 * @return string
	 */
	public function getContextSummaryField($errorOnly = false) {
		$html = new HTMLText('ContextSummaryField');

		// Get class name, if its a 'special case' type, blank it out.
		$class = '';
		$parent = $this->Parent();
		if ($parent && $parent->exists()) {
			$class = $parent->ListClassName;
			if ($class && $class[0] === '(') {
				$class = '';
			}
		}

		// Set color if error
		$color = '';
		$error = $this->getConfigError($class);
		if ($error) {
			if ($error === true || $error === 1) {
				$error = 'Error';
			}
			$color = '#C00';
		}

		$context = $this->getContext();
		$text = $error;
		if (!$errorOnly) {
			if ($text && $context) {
				$text .= ' -- '.$context;
			} else {
				$text .= $context;
			}
		}

        $html->setValue(sprintf(
            '<span %s>%s</span>',
            $color ? 'style="color: '.$color.';"' : '',
            htmlentities($text)
        ));
		return $html;
	}

	/**
	 * Return generalized information about the filter configuration 
	 *
	 * @return string
	 */
	public function getContext() {
		return '';
	}

	/** 
	 * Return an error message to show in CMS fields and in summary fields
	 *
	 * @return bool|string
	 */
	public function getConfigError($class) {
		return false;
	}
}