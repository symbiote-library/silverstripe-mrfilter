(function($){
	"use strict";

	/**
	 * Filters the records by the date range inputted.
	 *
	 * @return {array}
	 */
	$('.js-listfilter-filter').bind('ListFilterDateRange', function(e, records) {
		function convertDateToTime(date, dateFormat) {
			if (date && dateFormat) {
				// Convert from specific other date formats
				var dateBits;
				if (dateFormat === 'd/m/Y') {
					dateBits = date.split('/');
					date = dateBits[2]+'-'+dateBits[1]+'-'+dateBits[0];
				} else if (dateFormat === 'd-m-Y') {
					dateBits = date.split('-');
					date = dateBits[2]+'-'+dateBits[1]+'-'+dateBits[0];
				} else {
					console.log('ListFilterDateRange: Unsupported dateFormat "'+dateFormat+'" configured.');
				}
			}
			return Date.parse(date);
		}
		
		var filterStartDate = null;
		var filterEndDate = null;
		var dateFormat = null;

		// getStartDateAndEndDateFromInptus
		var $inputs = $(this).find('input');
		$inputs.each(function() {
			var $input = $(this);
			var name = $input.attr('name');
			if (name.indexOf('StartDate') != -1) {
				filterStartDate = $input.val();
				dateFormat = $input.data('dateformat');
			} else if (name.indexOf('EndDate') != -1) {
				filterEndDate = $input.val();
				if (!dateFormat) {
					dateFormat = $input.data('dateformat');
				}
			}
		});
		if (!filterStartDate && !filterEndDate) {
			return;
		}

		// Convert filter format to time()
		filterStartDate = convertDateToTime(filterStartDate, dateFormat);
		filterEndDate = convertDateToTime(filterEndDate, dateFormat);

		var fieldGroupID = $(this).data('fieldgroup-id');
		var visibleRecords = {};
		for (var r = 0; r < records.length; ++r) {
			var record = records[r];
			var fieldGroupIDs = record.FilterGroups;
			if (typeof fieldGroupIDs[fieldGroupID] === 'undefined') {
				console.log('ListFilterDateRange: Feature is missing FilterGroups['+fieldGroupID+'] data.');
				return;
			}
			var values = fieldGroupIDs[fieldGroupID].value;
			if (values.StartDate.length != values.EndDate.length) {
				console.log('ListFilterDateRange: Invalid start date and end dates, mismatching array sizes.');
				return;
			}

			var isInFilter = false;
			var startEndDateCount = values.StartDate.length;
			for (var i = 0; i < startEndDateCount; ++i) {
				var startDate = values.StartDate[i];
				startDate = convertDateToTime(startDate);
				var endDate = values.EndDate[i];
				endDate = convertDateToTime(endDate);
				if (filterStartDate && filterEndDate) {
					isInFilter = isInFilter || ((startDate <= filterStartDate && endDate >= filterEndDate) ||
								(startDate >= filterStartDate && startDate <= filterEndDate) ||
								(endDate >= filterStartDate && endDate <= filterEndDate));
				} else if (filterStartDate) {
					isInFilter = isInFilter || (startDate >= filterStartDate || endDate > filterStartDate);
				}  else if (filterEndDate) {
					isInFilter = isInFilter || (endDate <= filterEndDate || startDate < filterEndDate);
				}
			}
			if (isInFilter) {
				visibleRecords[record.ID] = record;
			}
		}
		return visibleRecords;
	});

	/**
	 * Filters the records by the IDs returned from the backend
	 *
	 * @return {array}
	 */
	$('.js-listfilter-filter').bind('ListFilterGroupIDs', function(e, records, backendFilter) {
		if (!backendFilter) {
			return null;
		}
		var visibleRecords = {};
		for (var r = 0; r < records.length; ++r) {
			var record = records[r];
			if (typeof backendFilter[record.ID] !== 'undefined') {
				visibleRecords[record.ID] = record;
			}
		}
		return visibleRecords;
	});

	/**
	 * Filters the records by the selected tags.
	 *
	 * @return {array}
	 */
	$('.js-listfilter-filter').bind('ListFilterTags', function(e, records) {
		var selectedFilters = null;
		var fieldGroupID = $(this).data('fieldgroup-id');
		var $inputs = $(this).find('input[type="checkbox"]');
		$inputs.each(function() {
			var matches = $(this).attr('name').match(/\[(.*?)\]/);
			var tagID = matches[1];
			if ($(this).prop('checked')) {
				if (selectedFilters === null) {
					selectedFilters = {};
				}
				if (typeof selectedFilters[fieldGroupID] === 'undefined') {
					selectedFilters[fieldGroupID] = {};
				}
				selectedFilters[fieldGroupID][tagID] = true;
			}
		});
		if (selectedFilters === null) {
			return null;
		}

		var visibleRecords = {};
		for (var r = 0; r < records.length; ++r) {
			var record = records[r];
			var fieldGroupIDs = record.FilterGroups;
			if (!fieldGroupIDs || typeof fieldGroupIDs[fieldGroupID] === 'undefined') {
				console.log('Feature is missing FilterGroups['+fieldGroupID+'] data.');
				return;
			}
			var isInFilter = false;
			var values = fieldGroupIDs[fieldGroupID].value;
			for (var i = 0; i < values.length; ++i) {
				var value = values[i];
				isInFilter = isInFilter || (selectedFilters !== null && typeof selectedFilters[fieldGroupID][value] !== 'undefined');
			}
			if (isInFilter) {
				visibleRecords[record.ID] = record;
			}
		}
		return visibleRecords;
	});

	/**
	 * Filters the records by whether or not their in range of the provided latitude
	 * or longitude.
	 *
	 * @return {array}
	 */
	$('.js-listfilter-filter').bind('ListFilterLatLngRadius', function(e, records) {
		function getDistanceInKmFromLatLngRadians(latitude1, longitude1, latitude2, longitude2) { 
			// Reference: http://stackoverflow.com/questions/12439801/how-to-check-if-a-certain-coordinates-fall-to-another-coordinates-radius-using-p
		    var dLat = latitude2 - latitude1;  
		    var dLon = longitude2 - longitude1;  

		    var a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(latitude1) * Math.cos(latitude2) * Math.sin(dLon/2) * Math.sin(dLon/2);  
		    var c = 2 * Math.asin(Math.sqrt(a));
		    var result = 6371 * c;

		    return result;  
		}

		var fieldGroupID = $(this).data('fieldgroup-id');
		var config = $(this).data('fieldgroup-config');
		if (typeof config.Radius === 'undefined') {
			console.log('"Radius" config variable is missing.');
		}
		var radiusInKm = parseInt(config.Radius, 10);

		var userLat = 0, 
			userLng = 0;
		var result = $(this).triggerHandler('getUserLatLng');
		if (typeof result !== 'undefined' && result) {
			userLat = result.Lat;
			userLng = result.Lng;
		} else {
			if (result === false) {
				// Allow the user-code to disable filtering by returning false with 'getUserLatLng'
				return null;
			}
			if (typeof config.Lat !== 'undefined' && typeof config.Lng !== 'undefined' && config.Lat && config.Lng) {
				userLat = config.Lat;
				userLng = config.Lng;
			}
		}

		if (userLat === 0 || userLng === 0) {
			return null;
		}

		// Degrees to Radians
		userLat *= 0.017453292519943295;
		userLng *= 0.017453292519943295;

		var visibleRecords = {};
		for (var r = 0; r < records.length; ++r) {
			var record = records[r];
			var fieldGroupIDs = record.FilterGroups;
			if (typeof fieldGroupIDs[fieldGroupID] === 'undefined') {
				console.log('Feature is missing FilterGroups['+fieldGroupID+'] data.');
				return;
			}
			var values = fieldGroupIDs[fieldGroupID].value;
			var distanceInKm = getDistanceInKmFromLatLngRadians(userLat, userLng, values.Lat, values.Lng);
			if (distanceInKm < radiusInKm) {
				visibleRecords[record.ID] = record;
			}
		}
		return visibleRecords;
	});
})(jQuery);