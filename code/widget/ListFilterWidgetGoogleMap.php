<?php

class ListFilterWidgetGoogleMap extends ListFilterWidget {
	private static $allowed_actions = array(
		'doGetFeatures',
		'doGetPopup',
	);

	/**
	 * A custom set list to use for 'getFeatureCollection'
	 */
	protected $list = null;

	/**
	 * Set if the widget loads all feature data and popup data inside 
	 * the data attributes (false) or via AJAX (true).
	 *
	 * Set to null to use 'default_ajax_disabled' config.
	 *
	 * @var boolean
	 */
	protected $ajax_enabled = null;

	/**
	 * The Google Maps API key
	 *
	 * @var string
	 */
	private static $api_key = null;

	/**
	 * Configure if the widget loads all feature data and popup data inside 
	 * the data attributes or via AJAX.
	 *
	 * @var boolean
	 */
	private static $default_ajax_disabled = false;

	/**
	 * Uses a basic 'LastEdited' check against the list for caching the 'getFeatureCollection'
	 * function.
	 *
	 * @var boolean
	 */
	// todo(Jake): easy/simple cache for features
	//private static $caching_enabled = false;

	/**
	 * {@inheritdoc}
	 */
	public function __construct() {
		parent::__construct();
		$this->addExtraClass('filtergroup-widget_googlemap js-listfilter-widget_googlemap');
	}

	/** 
	 * Retrieve FeatureCollection for Google Map
	 *
	 * @return string
	 */
	public function doGetFeatures($request) {
		$result = $this->getFeatureCollection();
		$result = json_encode($result);
		$this->getResponse()->addHeader('Content-Type', 'application/json');
		return $result;
	}

	/**
	 * Get popup for record
	 *
	 * @return string
	 */
	public function doGetPopup($request) {
		$data = $request->getVars();
		$id = isset($data['ID']) ? (int)$data['ID'] : null;
		if ($id === null) {
			$this->getResponse()->setStatusCode(400);
			return '';
		}
		$list = $this->FilteredList(array());
		$list = $list->filter(array('ID' => $id));
		$record = $list->first();
		if (!$record) {
			$this->getResponse()->setStatusCode(400);
			return '';
		}
		$template = $this->getPopupTemplate($record);
		if (!$template) {
			$this->getResponse()->setStatusCode(400);
			return '';
		}
		return $template->RAW();
	}

	/**
	 * @return array
	 */
	public function getFeatureCollection() {
		//$caching_enabled = $this->config()->caching_enabled;

		$list = $this->getList();
		if (!$list) {
			// NOTE(Jake): Ensures if any filters are applied with no user input, that they
			//			   still get applied for map markers.
			$list = $this->FilteredList(array());
			if (!$list) {
				throw new Exception('No form or record configured against '.__CLASS__.'.');
			}
		}

		$filterSetRecord = $this->getListFilterSet();
		$features = array();
		foreach ($list as $record) {
			if ($record->hasMethod('getGeoJSONFeatureArray')) {
				// Support GeoJSON module
				$feature = $record->getGeoJSONFeatureArray();
			} else {
				$feature = array(
					'type' => 'Feature',
					'properties' => array(),
					'geometry' => array(
						'type' => 'Point',
						'coordinates' => array(0, 0)
					)
				);
				$longitude = (float)$record->Lng;
				$latitude = (float)$record->Lat;
				if (!$longitude && !$latitude) {
					// Skip if lat/lng isn't set.
					continue;
				}
				$feature['geometry']['coordinates'] = array($longitude, $latitude);

				$properties = &$feature['properties'];
				$properties['ID'] = $record->ID;
				$properties['Name'] = $record->Title;

				// Use "updateGeoJSONFeatureArray" from GeoJSON module
				$record->invokeWithExtensions('updateGeoJSONFeatureArray', $feature);
			}
			if (!isset($properties['FilterGroups'])) {
				// Add frontend widget filtering information
				if ($filterSetRecord) {
					$filterData = $filterSetRecord->FilterData($record);
				}
				$properties['FilterGroups'] = $filterData;
			}
			$features[] = $feature;
		}
		$result = array(
			'type' => 'FeatureCollection',
			'features' => $features,
		);
		return $result;
	}

	/**
	 * @return ListFilterWidgetGoogleMap
	 */
	public function setAJAXEnabled($value) {
		$this->ajax_enabled = $value;
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
	 * @return HTMLText
	 */
	public function getPopupTemplate(DataObjectInterface $record) {
		return $record->renderWith(array(__CLASS__.'InfoWindow'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function onBeforeRender() {
		parent::onBeforeRender();
		Requirements::javascript(ListFilterUtility::MODULE_DIR.'/javascript/ListFilterWidgetGoogleMap.js');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDataAttributes() {
		// Setup default center lat/lng for map
		// NOTE(Jake): 0,0 = the map will set the center based on all the markers.
		$latitude = 0;
		$longitude = 0;
		$page = $this->getForm()->getController()->data();
		if ($page) {
			$latitude = $page->Lat;
			$longitude = $page->Lng;
		}
		$filterSetRecord = $this->getListFilterSet();
		if ((!$latitude || !$longitude) && $filterSetRecord) {
			$latitude = $filterSetRecord->Lat;
			$longitude = $filterSetRecord->Lng;
		}

		// Setup attributes
		$attributes = array();
		if ($this->getAJAXEnabled()) {
			$attributes = array(
				'features-url'	 => $this->Link('doGetFeatures'),
				'popup-url'		 => $this->Link('doGetPopup'),
				'popup-loading'  => $this->renderWith(array(__CLASS__.'InfoWindowLoading')),
			);
		} else {
			$attributes['features'] = $this->getFeatureCollection();
			$popupTemplates = array();
			foreach ($this->FilteredList() as $record) {
				$popupTemplate = $this->getPopupTemplate($record);
				if ($popupTemplate && $popupTemplate instanceof HTMLText) {
					$popupTemplate = $popupTemplate->RAW();
				}
				$popupTemplates[$record->ID] = $popupTemplate;
			}
			if ($popupTemplates) {
				$attributes['popup'] = $popupTemplates;
			}
		}

		$attributes = array_merge($attributes, array(
			'map-dependencies' => array(
				'markerclusterer' => array(
					'script' => Director::absoluteBaseURL().'/'.ListFilterUtility::MODULE_DIR.'/javascript/thirdparty/markerclusterer.min.js',
					'options' => array(
						// For more options, see: https://web.archive.org/web/20160829003318/https://googlemaps.github.io/js-marker-clusterer/docs/reference.html
						'imagePath' => Director::absoluteBaseURL().'/'.ListFilterUtility::MODULE_DIR.'/images/thirdparty/markerclusterer/m',
						//'gridSize' => 50, 
						//'maxZoom' => 12,
					),
				),
			),
			'map-parameters' => array(
				'zoom' => 6,
				'center' => array(
					'lat' => (float)$latitude,
					'lng' => (float)$longitude,
				),
			),
			'marker-parameters'	=> array(
				//'icon' => 'themes/mythemefolder/images/maps-icons/default.png'
			),
			'init-parameters'	=> array(
				'key' 	    => $this->getAPIKey(),
				'signed_in' => true,
				'libraries' => 'places',
				'callback'  => 'initSSMapWidget',
			),
		));

		$attributes = array_merge(parent::getDataAttributes(), $attributes);
		return $attributes;
	}

	/** 
	 * @return string|null
	 */
	public function getAPIKey() {
		$key = $this->config()->api_key;
		if ($key) {
			return $key;
		}
		// Fallback to Addressable module configuration.
		if (class_exists('GoogleGeocoding')) {
			$key = Config::inst()->get('GoogleGeocoding', 'google_api_key');
			if ($key) {
				return $key;
			}
		}
	}
}