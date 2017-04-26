<?php

class ListFilterWidgetGoogleMap extends ListFilterWidget {
	private static $allowed_actions = array(
		'doGetFeatures',
		'doGetPopup',
	);

	const CENTER_LATITUDE = 0;
	const CENTER_LONGITUDE = 1;

	/**
	 * Set if the widget loads all feature data and popup data inside 
	 * the data attributes (false) or via AJAX (true).
	 *
	 * Set to null to use 'default_ajax_disabled' config.
	 *
	 * @var boolean
	 */
	protected $ajaxEnabled = null;

	/**
	 * Set if the widget loads a template when clicking a marker.
	 *
	 * Set to null to use 'default_popup_disabled' config.
	 *
	 * @var boolean
	 */
	protected $popupEnabled = null;

	/**
	 * Set if the widget will not zoom on mouse scroll until
	 * being clicked/focused.
	 *
	 * Set to null to use 'default_is_scrollwheel_locked_disabled' config.
	 *
	 * @var boolean
	 */
	protected $isScrollwheelLocked = null;

	/**
	 * Set the center position of the map, if none set, it will default to
	 * the current set record or pages 'Lat' and 'Lng' fields.
	 *
	 * @var array
	 */
	protected $center = array(null, null);

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
	 * Configure the widget to disable use of popup template.
	 *
	 * @var boolean
	 */
	private static $default_popup_disabled = false;

	/**
	 * Configure the widget to either not lock/unlock scrollzoom on map focus/unfocus.
	 *
	 * @var boolean
	 */
	private static $default_is_scrollwheel_locked_disabled = false;

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
			$this->getResponse()->setStatusCode(404);
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
				$properties['Icon'] = array(
					// 'url' => '..'
					// 'scaledSize' => array(
					//		'width' => 32,
					//		'height' => 32,
					//) 
				);

				// Use "updateGeoJSONFeatureArray" from GeoJSON module
				$record->invokeWithExtensions('updateGeoJSONFeatureArray', $feature, $this);

				// 'IconURL' backwards compatibility
				if (isset($properties['IconURL'])) {
					$properties['Icon'] = array(
						'url' => $properties['IconURL'],
					);
				}
			}
			if ($filterSetRecord && !isset($properties['FilterGroups'])) {
				// Add frontend widget filtering information
				$properties['FilterGroups'] = $filterSetRecord->FilterData($record);
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
	 * @return boolean
	 */
	public function setIsScrollwheelLocked($value) {
		$this->isScrollwheelLocked = $value;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getIsScrollwheelLocked() {
		$result = $this->isScrollwheelLocked;
		if ($result === null) {
			return ($this->config()->default_is_scrollwheel_locked_disabled == false);
		}
		return $result;
	}

	/**
	 * @return $this
	 */
	public function setCenter($latitude, $longitude) {
		$this->center[self::CENTER_LATITUDE] = (double)$latitude;
		$this->center[self::CENTER_LONGITUDE] = (double)$longitude;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getCenter() {
		return $this->center;
	}

	/**
	 * @return $this
	 */
	public function setAJAXEnabled($value) {
		$this->ajaxEnabled = $value;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getAJAXEnabled() {
		$result = $this->ajaxEnabled;
		if ($result === null) {
			return ($this->config()->default_ajax_disabled == false);
		}
		return $result;
	}

	/**
	 * @return $this
	 */
	public function setPopupEnabled($value) {
		$this->popupEnabled = $value;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getPopupEnabled() {
		$result = $this->popupEnabled;
		if ($result === null) {
			return ($this->config()->default_popup_disabled == false);
		}
		return $result;
	}

	/**
	 * @return HTMLText
	 */
	public function getPopupTemplate(ViewableData $record) {
		return $record->renderWith($this->getTemplates(__CLASS__.'InfoWindow'));
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
		$center = $this->getCenter();
		if ($center && $center[self::CENTER_LATITUDE] !== null && $center[self::CENTER_LONGITUDE] !== null) {
			$latitude = $center[self::CENTER_LATITUDE];
			$longitude = $center[self::CENTER_LONGITUDE];
		} else {
			$record = $this->getRecord();
			if (!$record) {
				$record = $this->getPage();
			}
			if ($record) {
				$latitude = $record->Lat;
				$longitude = $record->Lng;
			}
		}

		// Setup attributes
		$attributes = array();
		if ($this->getAJAXEnabled()) {
			$attributes = array(
				'features-url'	 => $this->Link('doGetFeatures'),
			);
			if ($this->getPopupEnabled()) {
				$attributes['popup-url'] = $this->Link('doGetPopup');
				$attributes['popup-loading'] = $this->renderWith($this->getTemplates(__CLASS__.'InfoWindowLoading'));
			}
		} else {
			$attributes['features'] = $this->getFeatureCollection();
			$popupTemplates = array();
			$list = $this->getList();
			if (!$list) {
				$list = $this->FilteredList();
			}
			if ($this->getPopupEnabled()) {
				foreach ($list as $record) {
					if (!$record->ID) {
						if ($record instanceof DataObject) {
							throw new Exception('ID of record must not be 0.');
						} else if ($record instanceof ArrayData) {
							throw new Exception('Must set an explicit "ID" on ArrayData for use with map. Just use its position in the ArrayList.');
						} else {
							throw new Exception('Must set an explicit "ID" on data for use with map.');
						}
					}
					$popupTemplate = $this->getPopupTemplate($record);
					if ($popupTemplate && $popupTemplate instanceof HTMLText) {
						$popupTemplate = $popupTemplate->RAW();
					}
					// If the template outputs empty/whitespace-only HTML, don't attach any popup
					// template data, and implictly disable.
					if (is_string($popupTemplate)) {
						$popupTemplate = trim($popupTemplate);
					}
					if ($popupTemplate) {
						$popupTemplates[$record->ID] = $popupTemplate;
					}
				}
				if ($popupTemplates) {
					$attributes['popup'] = $popupTemplates;
				}
			}
		}

		$attributes = array_merge($attributes, array(
			'is-scrollwheel-locked' => $this->getIsScrollwheelLocked(),
			'map-dependencies' => array(
				'markerclusterer' => array(
					'script' => Director::absoluteBaseURL().'/'.ListFilterUtility::MODULE_DIR.'/javascript/thirdparty/markerclusterer.min.js',
					'options' => array(
						// For more options, see: https://web.archive.org/web/20160829003318/https://googlemaps.github.io/js-marker-clusterer/docs/reference.html
						'imagePath' => Director::absoluteBaseURL().'/'.ListFilterUtility::MODULE_DIR.'/images/thirdparty/markerclusterer/m',
						//'gridSize' => 60, 
						//'maxZoom' => 12,
						//'minimumClusterSize' => 2,
						//'zoomOnClick' => true,
						//'averageCenter' => false,
					),
				),
			),
			'map-parameters' => array(
				'zoom' => 6,
				'center' => array(
					'lat' => (double)$latitude,
					'lng' => (double)$longitude,
				)
			),
			'marker-parameters'	=> array(
				/*'icon' => array(
					'url' => 'themes/mythemefolder/images/maps-icons/default.png',
					'scaledSize' => array(
						'width'  => 32, 
						'height' => 32
					)
				),*/
			),
			'init-parameters'	=> array(
				'libraries' => 'places',
				'callback'  => 'initSSMapWidget',
			),
		));
		if ($apiKey = $this->getAPIKey()) {
			$attributes['init-parameters']['key'] = $apiKey;
		}

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