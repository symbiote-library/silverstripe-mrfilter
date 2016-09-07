<?php

class ListFilterWidgetGoogleMap extends ListFilterWidget {
	private static $allowed_actions = array(
		'doGetFeatures',
		'doGetPopup',
	);

	/**
	 * The Google Maps API key
	 *
	 * @var string
	 */
	private static $api_key = null;

	/**
	 * {@inheritdoc}
	 */
	public function __construct() {
		parent::__construct();
		$this->addExtraClass('js-listfilter-widget_googlemap');
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
		$filterSetRecord = $this->getRecord();
		$list = $filterSetRecord->BaseList();
		$list = $list->filter(array('ID' => $id));
		$record = $list->first();
		if (!$record) {
			$this->getResponse()->setStatusCode(400);
			return '';
		}
		return $record->renderWith(array(__CLASS__.'InfoWindow'))->RAW();
	}

	/**
	 * @return array
	 */
	public function getFeatureCollection() {
		$filterSetRecord = $this->getRecord();

		$features = array();
		// NOTE(Jake): Ensures if any filters are applied with no user input, that they
		//			   still get applied for map markers.
		$list = $filterSetRecord->FilteredList(array());
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
			}
			// Use "updateGeoJSONFeatureArray" from GeoJSON module
			$record->invokeWithExtensions('updateGeoJSONFeatureArray', $feature);
			if (!isset($properties['FilterGroups'])) {
				// Add frontend widget filtering information
				$filterData = $filterSetRecord->FilterData($record);
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
		$filterSetRecord = $this->getRecord();
		$attributes = array(
			'features-url'	 => $this->Link('doGetFeatures'),//'test.json', //$this->Link('doGetFeatures'), //'test.json',
			'popup-url'		 => $this->Link('doGetPopup'),
			'map-dependencies' => array(
				'markerclusterer' => array(
					'script' => '/'.ListFilterUtility::MODULE_DIR.'/javascript/thirdparty/markerclusterer.min.js',
					'options' => array(
						// For more options, see: https://web.archive.org/web/20160829003318/https://googlemaps.github.io/js-marker-clusterer/docs/reference.html
						'imagePath' => ListFilterUtility::MODULE_DIR.'/images/thirdparty/markerclusterer/m',
						//'gridSize' => 50, 
						//'maxZoom' => 12,
					),
				),
			),
			'map-parameters' => array(
				'zoom' => 6,
				'center' => array(
					'lat' => (float)$filterSetRecord->Lat,
					'lng' => (float)$filterSetRecord->Lng,
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
		);
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