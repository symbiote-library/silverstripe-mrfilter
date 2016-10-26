(function($){
	"use strict";

	$(document).on('ListFilterWidgetReinit', initMaps);

	var $mapWidgets = $('.js-listfilter-widget_googlemap');
	if (!$mapWidgets.length) {
		return;
	}

    // Get initialization parameters
    var parameters = $mapWidgets.first().data('init-parameters');
    var urlParameters = '?'+$.param(parameters);

    // Set callback so it can be used by Google Maps
    window[parameters.callback] = preInitMaps;

    // Load GeoJSON features
    var cacheAjaxURL = {};
    $mapWidgets.each(function(e) {
    	var $mapElement = $(this);

    	$mapElement.data('listfilter-records', false);

    	var features = $mapElement.data('features');
    	if (features) {
    		loadFeaturesForMap(this);
    	} else {
	    	var url = $mapElement.data('features-url');
	    	if (url) {
		    	if (typeof cacheAjaxURL[url] === 'undefined') {
		    		cacheAjaxURL[url] = false;
			    	$.ajax({
						type: 'GET',
						url: url,
						async: true,
						crossDomain: true,
						headers: {
						    'X-Requested-With': 'XMLHttpRequest'
						}
			        }).done(function(data) {
			        	cacheAjaxURL[url] = data;
			        	$mapWidgets.each(function() {
			        		if ($(this).data('features-url') === url) {
			        			loadFeaturesForMap(this);
			        		}
			        	});
			        }).fail(function() {
			        	cacheAjaxURL[url] = true;
			        });
			    }
			}
		}
    });

    // Initialize Google Map JS
	var googleMapScript = document.createElement('script');
    googleMapScript.setAttribute('type','text/javascript');
    googleMapScript.setAttribute('async', true);
    googleMapScript.setAttribute('src','https://maps.google.com/maps/api/js'+urlParameters);
    (document.getElementsByTagName('head')[0] || document.documentElement).appendChild(googleMapScript);

    function ClickMarker() {
    	var $mapElement = $(this.getMap().getDiv());
    	$mapElement.trigger('GoogleMapInfoWindowOpen', [this.record, $mapElement.data('infowindow')]);
    }

    function loadFeaturesForMap(mapElement) {
    	var $mapElement = $(mapElement);
    	if (!$mapElement.data('map') || $mapElement.data('listfilter-records')) {
			// Only add features if the "google.maps.Map" object exists
			// and if no features exist yet.
			return;
		}
		var featureData = $mapElement.data('features');
		if (!featureData) {
			// Get feature data if its been loaded for that URL
	    	var url = $mapElement.data('features-url');
	    	featureData = cacheAjaxURL[url];
    	}
    	if (featureData === false || featureData === true) {
    		return;
    	}
		var map = $mapElement.data('map');
		var dependencies = $mapElement.data('map-dependencies');
		var isMousezoomLocked = $mapElement.data('is-scrollwheel-locked');
		var popupEnabled = ($mapElement.data('popup-url') || $mapElement.data('popup'));
		var markerDefaultParameters = $mapElement.data('marker-parameters');
		if (!markerDefaultParameters || markerDefaultParameters.length === 0) {
			markerDefaultParameters = {};
		}
		var records = [];

	    $mapElement.data('listfilter-records', records);
	    for (var i = 0; i < featureData.features.length; ++i) {
	    	var feature = featureData.features[i];
	    	if (feature.geometry.type === 'Point') {
	    		// Add marker
	            var marker = new google.maps.Marker(markerDefaultParameters);
	            marker.setPosition({
	        		lat: feature.geometry.coordinates[1],
	        		lng: feature.geometry.coordinates[0]
	        	});
	            marker.setMap(map);

				if (popupEnabled) {
					// todo: Don't hook if using non-AJAX popup info and its blank/not-set
					marker.addListener('click', ClickMarker);
				}

				// Add record
				var recordProperties = feature.properties;
				var record = {};
				record.ID = recordProperties.ID;
				record.Properties = recordProperties;
				record.Marker = marker;
				if (recordProperties.IconURL !== '') {
					marker.setIcon(recordProperties.IconURL);
				}
				// Add info for live filtering
				if (typeof recordProperties.FilterGroups !== 'undefined') {
					record.FilterGroups = recordProperties.FilterGroups;
				}
				marker.record = record;
				records.push(record);
	    	} else {
	    		console.log('Unsupported feature type ' + feature.geometry.type);
	    	}
		}

		// Setup mouse zoom locking
		if (isMousezoomLocked) {
			map.setOptions({scrollwheel:false});
			google.maps.event.addListener(map, 'click', function (e) {
				if (!this.scrollwheel) {
					this.setOptions({scrollwheel:true});
				}
			});

			$(document).on('click', function(event) {
				if(map.scrollwheel && $mapElement.find(event.target).length === 0) {
					map.setOptions({scrollwheel:false});
				}
			});

			google.maps.event.addListener(map, 'mouseout', function (e) {
				this.setOptions({scrollwheel:false});
			});
		}

		// Setup cluster
		if (typeof dependencies.markerclusterer !== 'undefined') {
			var clusterMarkers = [];
			for (var cm = 0; cm < records.length; ++cm) {
				clusterMarkers.push(records[cm].Marker);
			}
			var markerClustererOptions = dependencies.markerclusterer.options;
			var markerCluster = new MarkerClusterer(map, clusterMarkers, markerClustererOptions);
			markerCluster.calculatorDefault = markerClustererCalculator;
			if (typeof markerClustererOptions.calculator === 'undefined') {
				markerCluster.setCalculator(markerCluster.calculatorDefault);
			}
			$mapElement.data('markerclusterer', markerCluster);
		}

		$mapElement.on('GoogleMapInfoWindowOpen', function(e, record, infoWindow) {
			var $mapElement = $(this);
			var map = $mapElement.data('map');

			var content = record.Properties.InfoWindow;
			if (!content) {
				var infowindow = $mapElement.data('popup');
				if (infowindow) {
					record.Properties.InfoWindow = infowindow[record.ID];
				} else {
					var url = $mapElement.data('popup-url');
					if (url) {
						url += '?ID='+record.ID;
						if (typeof cacheAjaxURL[url] !== 'undefined' && cacheAjaxURL[url] !== true) {
							if (cacheAjaxURL[url]) {
								record.Properties.InfoWindow = cacheAjaxURL[url];
							}
						} else {
							cacheAjaxURL[url] = false;
							$.ajax({
								type: 'GET',
								url: url,
								async: true,
								crossDomain: true,
								headers: {
									'X-Requested-With': 'XMLHttpRequest'
								}
							}).done(function(data) {
								cacheAjaxURL[url] = data;
								record.Properties.InfoWindow = data;
								$mapElement.trigger('GoogleMapInfoWindowOpen', [record, infoWindow]);
							}).fail(function() {
								cacheAjaxURL[url] = true;
							});
						}
					}
				}
	    	}

	        content = record.Properties.InfoWindow;
			if (content) {
				infoWindow.setContent(content);
			} else {
				infoWindow.setContent($mapElement.data('popup-loading'));
			}
			infoWindow.setPosition(record.Marker.getPosition());
			infoWindow.setOptions({pixelOffset: new google.maps.Size(0, -30)});
			infoWindow.open(map);
	    });

		$mapElement.on('GoogleMapShowAllVisibleMarkers', function(e, callback) {
			var $mapElement = $(this);
			var records = $mapElement.data('listfilter-records');
			if (records.length === 0) {
				return;
			}
			var bounds = new google.maps.LatLngBounds();
			var visibleMarkerCount = 0;
			for (var i = 0; i < records.length; ++i) {
				var marker = records[i].Marker;
				if (marker.getVisible()) {
					bounds.extend(marker.getPosition());
					++visibleMarkerCount;
				}
			}
			if (visibleMarkerCount > 0) {
				var map = $mapElement.data('map');
				map.fitBounds(bounds);
	            map.setCenter(bounds.getCenter());
	            return true;
        	}
        	return false;
		});

		google.maps.event.addListenerOnce(map, 'tilesloaded', function (e) {
			var map = this;
			var $mapElement = $(map.getDiv());
			// After geoJSON has loaded, if lat/lng hasnt been set on the map
			// make the center based on all the markers/points.
			var center = map.getCenter();
			var lat = Math.floor(center.lat());
			var lng = Math.floor(center.lng());
			if (lat === 0 && lng === 0) {
				$mapElement.trigger('GoogleMapShowAllVisibleMarkers');
			}
	    });

	    google.maps.event.addListenerOnce(map, 'bounds_changed', function(event) {
	    	// Ensure the map fits when fitting the bounds based on markers
	    	// (map.fitBounds is async, so we need to set the zoom once its finished)
	    	this.setZoom(this.getZoom() - 1);
			if (this.getZoom() > 15) {
				this.setZoom(15);
			}
	    });

		$mapElement.trigger('ListFilterWidgetInit');
		return true;
    }

    function markerClustererCalculator(markers, numStyles) {
    	var index = Math.min(0, numStyles);
		var count = 0;
		for (var i = 0; i < markers.length; ++i) {
			var marker = markers[i];
			if (marker.getVisible()) {
				count += 1;
			}
		}
		return {
			text: count.toString(),
			index: index
		};
    }

	function preInitMaps() {
		// Get dependendant JS files
    	var mapDependencies = {};
    	$mapWidgets.each(function() {
    		var dependencies = $(this).data('map-dependencies');
    		for (var name in dependencies) { 
    			mapDependencies[name] = dependencies[name]; 
    		}
    	});
    	var mapDependenciesCount = 0;
    	var mapDependenciesLoadedCount = 0;

    	function dependencyLoadedCallback() {
    		++mapDependenciesLoadedCount;
    		if (mapDependenciesLoadedCount === mapDependenciesCount) {
    			initMaps();
    		}
    	}
    	for (var name in mapDependencies) { 
    		var dependency = mapDependencies[name];
    		$.getScript(dependency.script, dependencyLoadedCallback);
    		++mapDependenciesCount;
    	}
    	if (mapDependenciesCount === 0) {
    		initMaps();
    	}
	}

	function initMaps() {
		if (typeof google === 'undefined') {
			// Ignore if not yet initialized
			return;
		}

		// Load initialize maps
		$mapWidgets = $('.js-listfilter-widget_googlemap');
		$mapWidgets.each(function() {
			var $mapElement = $(this);
			if ($mapElement.data('map')) {
				// Ignore if already initialized
				return;
			}

			// Update visibility of markers based on filter criterion.
			$(this).on('ListFilterRecordsUpdate', function(e, records, setVisible) {
				for (var i = 0; i < records.length; ++i) {
					records[i].Marker.setVisible(setVisible);
				}
				var markerCluster = $(this).data('markerclusterer');
				if (markerCluster) {
					// Repaint the numbers on the clusters
					// NOTE(Jake): Not calling markerCluster.redraw() as it looks bad
					//			   when it refreshes.
					for (var c = 0; c < markerCluster.clusters_.length; ++c) {
						markerCluster.clusters_[c].updateIcon();
					}
				}
			});

			var mapParameters = $mapElement.data('map-parameters');
			var map = new google.maps.Map($mapElement[0], mapParameters);
			$mapElement.data('map', map);

			// Store one reusable infowindow so we don't end up iht multiple open
			var infoWindow = new google.maps.InfoWindow();
			$mapElement.data('infowindow', infoWindow);

			// Setup features/markers
			loadFeaturesForMap($mapElement[0]);
		});
	}
})(jQuery);