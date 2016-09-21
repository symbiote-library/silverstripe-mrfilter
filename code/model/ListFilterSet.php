<?php

class ListFilterSet extends DataObject {
	private static $db = array(
		'ListCMSTitle'  => 'Varchar',
		'ListClassName' => 'Varchar(60)',
		'ListLimitPerPage' => 'Int',
	);

	private static $has_many = array(
		'ListFilters' => 'ListFilterBase',
	);

	private static $defaults = array(
		'ListClassName' => 'SiteTree',
	);

	private static $summary_fields = array(
		'ID' => 'ID',
		'ListCMSTitle' => 'Title',
		'ListFiltersString' => 'List Filters',
	);

	/**
	 *
	 */
	protected $listFilters = null;

	/** 
	 * @return FieldList
	 */
	public function getCMSFields() {
		$self = &$this;
		$self->beforeUpdateCMSFields(function($fields) use ($self) {
			$self->updateCMSFields($fields);
		});
		$fields = parent::getCMSFields();
		return $fields;
	}

	/** 
	 * @return FieldList
	 */
	public function updateCMSFields(FieldList $fields) {
		$fields->removeByName(array('ListClassName', 'ListFilters'));
		$classes = ClassInfo::subclassesFor('DataObject');
		$fields->addFieldToTab('Root.Main', TextField::create('ListCMSTitle', 'Title')->setRightTitle('A title to identify the filter set across the CMS.'));
		$fields->addFieldToTab('Root.Main', DropdownField::create('ListClassName', 'List Type')->setSource($classes));
		$fields->addFieldToTab('Root.Main', TextField::create('ListLimitPerPage', 'List Per Page')->setRightTitle('0 = Show all, no pagination.'));
		if ($this->owner->isInDB()) {
			$config = new GridFieldConfig_RecordEditor();
			if (class_exists('GridFieldAddNewMultiCLass')) {
				$config->removeComponentsByType('GridFieldAddNewButton');
				$config->addComponent(new GridFieldAddNewMultiCLass());
				$config->addComponent(new GridFieldOrderableRows());
			}
			$fields->addFieldToTab('Root.Main', GridField::create('ListFilters', 'Filter Groups', $this->owner->getComponents('ListFilters'), $config));
		}
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		$title = $this->ListCMSTitle;
		if (!$title) {
			// If no title, show telling information about the filter
			$title = $this->ListClassName;
			$title .= ' -- '.$this->getListFiltersString();
		}
		return $title;
	}

	/**
	 * @return string
	 */
	public function getListFiltersString() {
		$listFilters = $this->getComponents('ListFilters')->map('ID', 'singular_name');
		if ($listFilters instanceof SS_Map) {
			$listFilters = $listFilters->toArray();
		}
		$result = implode(', ', $listFilters);
		return $result;
	}

	/**
	 * @return ListFilterSet
	 */
	public function setCaller($caller) {
		$this->caller = $caller;
		return $this;
	}

	/**
	 * @return object
	 */
	public function getCaller() {
		return $this->caller;
	}

	/**
	 * Get all the list filters, but only once. This ensures they keep state across
	 * execution.
	 *
	 * ie. ListFilterBase has an 'init' function. I want any variables set in that function
	 *     to carry across to the whole program flow.
	 *
	 * @return ArrayList
	 */
	public function ListFiltersPersist() {
		if ($this->listFilters !== null) {
			return $this->listFilters;
		}
		$result = new ArrayList($this->getComponents('ListFilters')->toArray());
		return $this->listFilters = $result;
	}

	/** 
	 * @return SS_List
	 */
	public function BaseList() {
		$list = DataList::create($this->owner->ListClassName);
		$this->extend('updateBaseList', $list);
		return $list;
	}

	/**
	 * Runs filters over a base SS_List based on user-input.
	 *
	 * @return SS_List
	 */
	public function FilteredList(array $data, $caller) {
		$list = $this->owner->BaseList();
		$list = $this->applyFilterToList($list, $data, $caller);
		return $list;
	}

	/**
	 * @return SS_List
	 */
	public function PaginatedFilteredList(array $data, $caller) {
		$list = $this->FilteredList($data, $caller);
		$list = PaginatedList::create($list, $data);
		if ($this->ListLimitPerPage > 0) {
			$list->setPageLength($this->ListLimitPerPage);
		} else {
			$list->setPageLength(0);
		}
		return $list;
	}

	/**
	 * When generating map pins / widget data, this function will
	 * add additional data so that the pins can be filtered in JavaScript.
	 *
	 * @return array
	 */
	public function FilterData(DataObject $record) {
		$result = array();
		foreach ($this->ListFiltersPersist() as $filterGroup) {
			$data = $filterGroup->getFilterData($record);
			if ($data !== null && (!isset($data['value']) || $data['value'] !== null)) {
				$result[$filterGroup->ID] = $data;
			}
		}
		return $result;
	}

	/**
	 * Runs each filter group seperately and stores any backend filter data that
	 * may exist.
	 *
	 * ie. Solr keyword search returns IDs of records that matches the search.
	 *
	 * @return SS_List
	 */
	public function FilterBackendData(array $data, $caller) {
		$this->setCaller($caller);
		$allFilterGroupData = $this->unNamespaceFilterFields($data);

		$result = array();
		$baseList = $this->owner->BaseList();
		foreach ($this->owner->ListFiltersPersist() as $filterGroup) {
			$id = (int)$filterGroup->ID;
			$filterGroupData = isset($allFilterGroupData[$id]) ? $allFilterGroupData[$id] : array();
			$filterResultData = $filterGroup->getFilterBackendData($baseList, $filterGroupData);
			if ($filterResultData !== null) {
				$result[$id] = $filterResultData;
			}
		}
		$this->setCaller(null);
		return $result;
	}

	/**
	 * Apply filters to any given list based on user-input
	 */
	public function applyFilterToList(SS_List $list, array $data, $caller) {
		$this->setCaller($caller);
		$allFilterGroupData = $this->unNamespaceFilterFields($data);

		// Track shared filters
		$sharedFilters = array();

		// Apply filter based on data sent through
		foreach ($this->owner->ListFiltersPersist() as $filterGroup) {
			$filterGroupData = isset($allFilterGroupData[$filterGroup->ID]) ? $allFilterGroupData[$filterGroup->ID] : array();
			$filterResult = $filterGroup->applyFilter($list, $filterGroupData);
			if ($filterResult !== null) {
				if ($filterResult instanceof ListFilterShared) {
					$sharedFilters[$filterResult->_uid] = $filterResult;
				} else if ($filterResult instanceof SS_List) {
					$list = $filterResult;
				} else {
					throw new Exception('Invalid type returned from '.$filterGroup->class.'::applyFilter()');
				}
			}
		}

		// Apply filters that were shared across filter groups
		// ie. concat Solr filters into a single query.
		foreach ($sharedFilters as $sharedFilter) {
			$filterResult = $sharedFilter->applyFilter($list);
			if ($filterResult !== null) {
				if ($filterResult instanceof SS_List) {
					$list = $filterResult;
				} else {
					throw new Exception('Invalid type returned from '.$sharedFilter->class.'::applyFilter()');
				}
			}
		}
        
        // Allow the filters to analyse the final list and update itself accordingly
        foreach ($this->owner->ListFiltersPersist() as $filterGroup) {
            $filterGroup->finaliseFilter($list);
        }

		$this->setCaller(null);
		return $list;
	}

	/**
	 * @return array
	 */
	public function unNamespaceFilterFields(array $data) {
		// Un-namespace the data specific to the ListFilterGroups.
		$allFilterGroupData = array();
		if ($data) {
			foreach ($data as $fieldName => $value) {
				$fieldNameParts = explode('_', $fieldName);
				if ($fieldNameParts[0] === 'FilterGroup' && isset($fieldNameParts[1]) && isset($fieldNameParts[2])) {
					$filterGroupID = (int)$fieldNameParts[1];
					$fieldName = $fieldNameParts[2];
					$allFilterGroupData[$filterGroupID][$fieldName] = $value;
				}
			}
		}
		return $allFilterGroupData;
	}
}