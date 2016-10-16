<?php

// Requires: https://github.com/nyeholt/silverstripe-solr

if (class_exists('SolrSearchService')) {

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
	 * Sort by the nearest to furthest if true.
	 *
	 * @var boolean
	 */
	private static $default_sort_by_nearest = false;

	/**
	 * If null, fallback to using 'default_sort_by_nearest'.
	 *
	 * @var boolean|null
	 */
	protected $sort_by_nearest = null;

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$self = &$this;
		$self->beforeUpdateCMSFields(function($fields) use ($self) {
			$fields->addFieldToTab('Root.Main', TextField::create('Radius', 'Radius (in kilometres)')->setRightTItle('0 = Use default radius ('.$self->config()->default_radius.'km)'));
		});
		$fields = parent::getCMSFields();
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
	public function getFilterConfig(array $data) {
		return array(
			'Radius' => $this->Radius(),
			// NOTE(Jake): Pass a Lat/Lng to filter by nearby locations based on the Radius for the Widget.
			// 'Lat' => 0.0000,
			// 'Lng' => 0.0000,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterData(DataObject $record) {
		return array(
			'value' => array(
				'Lat' 	 => deg2rad($record->Lat),
				'Lng' 	 => deg2rad($record->Lng),
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
	 * Sort by the nearest to furthest if true.
	 *
	 * @return ListFilterSolrGeospatial
	 */
	public function setSortByNearest($bool) {
		$this->sort_by_nearest = $bool;
		return $this;
	}

	/**
	 * @return boolean|null
	 */
	public function getSortByNearest() {
		if ($this->sort_by_nearest === null) {
			return $this->config()->default_sort_by_nearest;
		}
		return $this->sort_by_nearest;
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data) {
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
		if ($this->getSortByNearest()) {
			$builder->sortBy('geodist()', 'ASC');
		}
		return $sharedFilter;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterBackendData(SS_List $list, array $data) {
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
	 * NOTE: Expects 'return array($latitude, $longitude)'
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
		return 'ListFilterLatLngRadius';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfigError($class) {
		if (!$this->Parent()->exists()) {
			return false;
		}
		if (!$class) {
			return false;
		}
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

}