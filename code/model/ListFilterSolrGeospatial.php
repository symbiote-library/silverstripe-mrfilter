<?php

if (!class_exists('SolrSearchService')) {
	return;
}

class ListFilterSolrGeospatial extends ListFilterBase {
	private static $db = array(
		'Radius' => 'Int',
	);

	private static $defaults = array(
		'Title'  => 'Filter by Location',
		'Radius' => 0,
	);

	/**
	 * Hide from 'GridFieldAddClassesButton'
	 *
	 * @var string
	 */
	private static $hide_ancestor = 'ListFilterSolrGeospatial';

	/** 
	 * Radius of the search
	 *
	 * @var int
	 */
	private static $default_radius = 20;

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Main', TextField::create('Radius', 'Radius (in kilometres)')->setRightTItle('0 = Use default radius ('.$this->config()->default_radius.'km)'));
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		$fields->push(TextField::create('Location', 'Suburb or Postcode'));
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterData(DataObject $record) {
		return array(
			'value' => array(
				'Lat' 	 => $record->Lat,
				'Lng' 	 => $record->Lng,
				//'Radius' => $this->radius,
			)
		);
	}

	/**
	 * Get radius
	 *
	 * @return int
	 */
	public function Radius() {
		$radius = $this->getField('Radius');
		if ($radius <= 0) {
			$radius = $this->config()->default_radius;
		}
		return (int)$radius;
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data, $caller) {
		$latLng = $this->getUserLatLng($data);
		if (!$latLng) {
			return;
		}

		/**
		 * @var $sharedFilter ListFilterSharedSolr 
		 */
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
		$builder->restrictNearPoint(implode(',', $latLng), 'LatLng_p', $this->Radius());
		return $sharedFilter;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterBackendData(SS_List $list, array $data, $caller) {
		$latLng = $this->getUserLatLng($data);
		if (!$latLng) {
			return;
		}
		/**
		 * @var $myFilter ListFilterSharedSolr 
		 */
		$myFilter = $this->SharedFilter('ListFilterSharedSolr', $this->ID); 
		$builder = $myFilter->getQueryBuilder();
		$builder->restrictNearPoint(implode(',', $latLng), 'LatLng_p', $this->Radius());

		$list = $myFilter->applyFilter($list);
		$ids = array();
		foreach ($list as $record) {
			$ids[$record->ID] = true;
		}
		return $ids;
	}

	/**
	 * Get users latitude and longitude
	 *
	 * @return array
	 */
	public function getUserLatLng(array $data) {
		throw new Exception($this->class.' must override '.__FUNCTION__);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getJavascriptCallback() {
		return 'ListFilterGroupIDs';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfigError() {
		$class = $this->getListClassName();
		$searchableFields = singleton('SolrSearchService')->getSearchableFieldsFor($class);
		if (!isset($searchableFields['LatLng'])) {
			return $class.' must have "ListFilterSolrGeospatialExtension" applied or add a "LatLng_p" solr search field.';
		}
		$latLng = $searchableFields['LatLng'];
		if ($latLng !== 'LatLng_p') {
			return $class.' "LatLng" field must be equal to "LatLng_p". ie. updateSolrSearchableFields() should be working like the "ListFilterSolrGeospatialExtension" extension.';
		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getContext() {
		if ($this->isInDB()) {
			return 'Radius: '.$this->Radius().'km';
		}
	}
}