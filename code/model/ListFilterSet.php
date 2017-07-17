<?php

class ListFilterSet extends DataObject {
	private static $db = array(
		'ListCMSTitle'  => 'Varchar',
		'ListClassName' => 'Varchar(60)', // todo(Jake): change fieldname to 'ListType'
		'ListLimitPerPage' => 'Int',
	);

	private static $has_many = array(
		'ListFilters' => 'ListFilterBase',
	);

	private static $defaults = array(
		'ListClassName' => '(Children)',
	);

	private static $summary_fields = array(
		'ID' => 'ID',
		'ListCMSTitle' => 'Title',
		'ListFiltersString' => 'List Filters',
	);

	/**
	 * @var ListFilterForm
	 */
	protected $form = null;

	/**
	 * Store each of the 'ListFilters()' in an ArrayList to keep their
	 * modified state persistent.
	 *
	 * @var ArrayList
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

		$listTypes = array();
		$listTypes['(Children)'] = '(All children of page being viewed)';
		$listTypes = array_merge($listTypes, ClassInfo::subclassesFor('DataObject'));

		$fields->addFieldToTab('Root.Main', TextField::create('ListCMSTitle', 'Title')->setRightTitle('A title to identify the filter set across the CMS.'));
		$fields->addFieldToTab('Root.Main', DropdownField::create('ListClassName', 'List Type')->setSource($listTypes));
		$fields->addFieldToTab('Root.Main', TextField::create('ListLimitPerPage', 'List Per Page')->setRightTitle('0 = Show all, no pagination.'));
		if ($this->isInDB()) {
			$config = new GridFieldConfig_RecordEditor();
			$fields->addFieldToTab('Root.Main', $gridField = GridField::create('ListFilters', 'Filter Groups', $this->getComponents('ListFilters'), $config));
			$config->removeComponentsByType('GridFieldAddNewButton');
			if (class_exists('GridFieldAddNewMultiCLass')) {
				$config->addComponent(new GridFieldAddNewMultiCLass());
				$config->addComponent(new GridFieldOrderableRows());
			} else {
				$gridField->setDescription('You must install the <a href="https://github.com/silverstripe-australia/silverstripe-gridfieldextensions" target="_blank">Grid Field Extensions</a> module to use this.');
			}
		} else {
			$fields->addFieldToTab('Root.Main', LiteralField::create('ListFilters_Help', 'You must save the filter set first to add filter groups.'));
		}

		// Show every page that is using this list filter set
		// (must use 'ListFilterSetExtension')
		if ($this->isInDB()) {
			$pagesAttachedToList = $this->PagesAttachedTo();
			$fields->addFieldToTab('Root.LinkedTo', $gridField = GridField::create('LinkedTo', 'Linked To', $pagesAttachedToList));
			$gridField->setDescription('The pages that are currently using this List Filter Set.');
			$gridField->setModelClass('SiteTree');
			if ($pagesAttachedToList->count() > 0) {
				$fields = array(
					'getTreeTitle' => _t('SiteTree.PAGETITLE', 'Page Title'),
					'singular_name' => _t('SiteTree.PAGETYPE'),
					'LastEdited' => _t('SiteTree.LASTUPDATED', 'Last Updated'),
				);
				$config = $gridField->getConfig();
				$columns = $config ->getComponentByType('GridFieldDataColumns');
				$gridField->getConfig()->getComponentByType('GridFieldSortableHeader')->setFieldSorting(array('getTreeTitle' => 'Title'));
				$columns->setDisplayFields($fields);
				$columns->setFieldCasting(array(
					'Created' => 'Datetime->Ago',
					'LastEdited' => 'Datetime->FormatFromSettings',
					'getTreeTitle' => 'HTMLText'
				));
				$columns->setFieldFormatting(array(
					'getTreeTitle' => function($value, &$item) {
						return sprintf(
							'<a class="action-detail" href="%s">%s</a>',
							Controller::join_links(
								singleton('CMSPageEditController')->Link('show'),
								(int)$item->ID
							),
							$item->TreeTitle // returns HTML, does its own escaping
						);
					}
				));
			}
		}
	}

	/**
	 * Return a list of the pages this list filter is used on.
	 *
	 * @return ArrayList
	 */
	public function PagesAttachedTo() {
		$pagesList = array();
		$originalMode = Versioned::current_stage();
        Versioned::reading_stage('Stage');

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'SiteTree')) {
                $object = singleton($class);
                $classes = ClassInfo::subclassesFor('ListFilterSetExtension');
                $hasListFilterSet = false;

                foreach ($classes as $extension) {
                    $hasListFilterSet = ($hasListFilterSet || $object->hasExtension($extension));
                }

                if ($hasListFilterSet) {
                    foreach ($class::get()->filter('ListFilterSetID', $this->ID) as $page) {
                    	$pagesList[] = $page;
                	}
                }
            }
        }

        Versioned::reading_stage($originalMode);
        return ArrayList::create($pagesList);
	}

	/**
	 * @return string
	 */
	public function getListClass() {
		try {
			$list = $this->SpecialList();
			if ($list) {
				return $list->dataClass();
			}
		} catch (LogicException $e) {
			// Ignore logic exceptions
		}
		return $this->ListClassName;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		$title = $this->ListCMSTitle;
		if (!$title) {
			// If no title, show telling information about the filter
			$title = $this->ListClassName;
			if ($title) {
				$title .= ' -- ';
			}
			$title .= $this->getListFiltersString();
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
	 * Get the current content controller
	 *
	 * @return Controller
	 */
	public function getContentController() {
		$controller = null;
		$form = $this->getForm();
		if ($form) {
			$controller = $form->getController();
		} else if (Controller::has_curr()) {
			$controller = Controller::curr();
		}
		if ($controller && ($controller instanceof ContentController || $controller->hasMethod('data'))) {
			return $controller;
		}
		return null;
	}

	/**
	 * @return $this
	 */
	public function getForm() {
		return $this->form;
	}

	/**
	 * @return $this
	 */
	public function setForm($form) {
		$this->form = $form;
		return $this;
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
		$list = $this->SpecialList();
		if (!$list) {
			$list = DataList::create($this->ListClassName);
		}
		$this->extend('updateBaseList', $list);
		return $list;
	}

	/**
	 * If the ListClassName is set to a 'special list' type, use that list.
	 *
	 * @return SS_List|boolean
	 */
	protected $_cache_special_list = null;
	public function SpecialList() {
		if ($this->_cache_special_list !== null) {
			return clone $this->_cache_special_list;
		}
		$listType = $this->getField('ListClassName');
		if (!$listType || $listType[0] !== '(') {
			return false;
		}
		$listType = substr($listType, 1, -1); // remove ( and )

		// Special List Types
		$list = null;
		switch ($listType) {
			case 'Children':
				$controller = $this->getContentController();
				if (!$controller) {
					return false;
				}
				$dataRecord = $controller->data();
				if (!$dataRecord || !$dataRecord->exists()) {
					throw new LogicException('No data record found on '.$controller->class.'. Unable to determine children for listing.');
				}
				if ($dataRecord->hasMethod('listFilterChildren')) {
					throw new Exception('todo(Jake): add call listFilterChildren() and test');
					//$list = $dataRecord->listFilterChildren($list);
				} else {
					if (!$dataRecord->hasExtension('Hierarchy')) {
						throw new LogicException('Cannot determine children of '.$dataRecord->ClassName.' as it does not use the "Hierarchy" extension.');
					}

					$baseClass = 'SiteTree';

					// The children list (Hierarchy::stageChildren) uses 'SiteTree' as the 'dataClass'
					// but we might want to access data that's specific to a BlogPost or CalendarEvent
					// ...so if the allowed_children only allow one specific type, switch the 
					// $baseClass/dataClass() to that.
					$allowedChildren = $dataRecord->stat('allowed_children');
					$allowedChildren = array_flip($allowedChildren);
					foreach ($allowedChildren as $class => $_) {
						if ($class && isset($class[0]) && $class[0] === '*') {
							// If there is two records:
							// - *CalendarEvent
							// - CalendarEvent
							//
							// Remove the non * one.
							//
							$class = ltrim($class, '*');
							unset($allowedChildren[$class]);
						}
					}
					$allowedChildren = array_keys($allowedChildren);
					if (count($allowedChildren) == 1) {
						$baseClass = reset($allowedChildren);
					}
					$excludeSubclasses = false;
					if ($baseClass && isset($baseClass[0]) && $baseClass[0] === '*') {
						$baseClass = ltrim($baseClass, '*');
						$excludeSubclasses = true;
					}

					// Manual recreation of: Hierarchy::stageChildren(true);
					$list = $baseClass::get()
								->filter('ParentID', (int)$dataRecord->ID)
								->exclude('ID', (int)$dataRecord->ID);
					if ($excludeSubclasses) {
						$list = $list->filter(array('ClassName' => $baseClass));
					}
					$showAll = true;
					$dataRecord->invokeWithExtensions('augmentStageChildren', $list, $showAll);
				}
			break;

			default:
				throw new Exception('Invalid special list type "'.$listType.'".');
			break;
		}
		$this->_cache_special_list = $list;
		return clone $this->_cache_special_list;
	}

	/**
	 * Runs filters over a base SS_List based on user-input.
	 *
	 * @return SS_List
	 */
	public function FilteredList(array $data, $caller) {
		$list = $this->BaseList();
		$list = $this->applyFilterToList($list, $data, $caller);
		$this->extend('updateFilteredList', $list);
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
		$baseList = $this->BaseList();
		foreach ($this->ListFiltersPersist() as $filterGroup) {
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
	 * Generates a link to edit this page in the CMS.
	 *
	 * @return string
	 */
	public function CMSEditLink() {
		return Controller::join_links(Controller::join_links(singleton('ListFilterAdmin')->Link(), 'ListFilterSet/EditForm/field/ListFilterSet/item/', $this->ID, '/edit'));
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
		foreach ($this->ListFiltersPersist() as $filterGroup) {
			$filterConfigError = $filterGroup->getConfigError($list->dataClass());
			if ($filterConfigError) {
				throw new LogicException($filterConfigError);
			}

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
        foreach ($this->ListFiltersPersist() as $filterGroup) {
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