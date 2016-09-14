(function($){
	"use strict";

	var debug, debugLog;
	debug = $('.js-listfilter-form').data('debug');
	if (debug) {
		debugLog = function(message) {
			console.log(message);
			alert(message);
		};
	} else {
		debugLog = function() {};
	}

	$(document).ready(function() {
		formsInit();
	});

	function formSubmit(e) {
		var $form = $(this);

		e.preventDefault();
		$.support.cors = true;
		$.ajax({
			type: 'GET',
			url: $form.attr('action'),
			crossDomain: true,
			headers: {
			    'X-Requested-With': 'XMLHttpRequest'
			},
			data: $form.serialize()
        }).done(function(data) {
        	var template, filterGroups, count, totalItems;
        	if (typeof data === 'object') {
        		filterGroups = data.FilterGroups;
        		template = data.Template;
        		count = data.Count;
        		totalItems = data.TotalItems;
        	} else {
        		totalItems = null;
        		count = null;
        		filterGroups = null;
        		template = data;
        	}
        	if (template.indexOf('<form') > -1) {
        		$form.replaceWith(data);
        		// todo(Jake): figure out how to get JS functionality (like jQuery UI datepicker, etc) to not break across elements.
        		//			   maybe diff the HTML and insert $Message stuff where appropriate?
        		formsInit();
        	} else {
        		// Update listing areas specifically using the same 'data-listfilter-id'
	        	var $relatedListing = $('.js-listfilter-listing[data-listfilter-id="'+$form.data('listfilter-id')+'"]');
	        	$relatedListing.html(template);
	        	// Update all listings in global scope (ie. without a 'data-listfilter-id' value)
	        	var $globalListing = $('.js-listfilter-listing:not([data-listfilter-id])');
	        	$globalListing.html(template);
	        	if ($relatedListing.length + $globalListing.length === 0) {
	        		debugLog('Missing .js-listfilter-listing element. Unable to put form result anywhere.');
	        	}
	        	if (count !== null) {
	        		// Update records returned count
	        		var $relatedListingCount = $('.js-listfilter-listing-count[data-listfilter-id="'+$form.data('listfilter-id')+'"]');
	        		$relatedListingCount.html(count);
	        		var $globalListingCount = $('.js-listfilter-listing-count:not([data-listfilter-id])');
	        		$globalListingCount.html(count);
	        	}
	        	if (totalItems !== null) {
	        		// Update records returned count
	        		var $relatedListingTotal = $('.js-listfilter-listing-totalitems[data-listfilter-id="'+$form.data('listfilter-id')+'"]');
	        		$relatedListingTotal.html(totalItems);
	        		var $globalListingTotal = $('.js-listfilter-listing-totalitems:not([data-listfilter-id])');
	        		$globalListingTotal.html(totalItems);
	        	}
        	}
        	if (filterGroups !== null) {
        		$form.data('listfilter-backend', filterGroups);
        		$form.trigger('ListFilterFormUpdate');
        	}
        	$form.trigger('ListFilterAJAXDone', [data]);
        }).fail(function(x, e, exception) {
        	$form.trigger('ListFilterAJAXFail', [x, e, exception]);
            if (debug) {
				if(x.status === 0){
				    alert('You are offline!!\n Please Check Your Network.');
				} else if(x.status == 404){
					alert('Requested URL not found.');
				} else if(x.status == 500){
					//$result.html(x.responseText);
					alert('Internal Server Error.');
				} else if(e == 'parsererror'){
					//$result.html(x.responseText);
					alert('Error.\nParsing JSON Request failed.');
				} else if(e == 'timeout'){
					alert('Request Time out.');
				} else {
					alert('Unknown Error.\n'+x.responseText);
				}
         	}
        });
	}

	function formFieldChange(e) {
		var $filterGroupHolder = $(this).parents('.js-listfilter-filter').first();
		var $form = $(this.form);
		if (!$filterGroupHolder.length) {
			debugLog('Missing .js-listfilter-filter as parent element. ListFilterBase_holder must have been modified incorrectly.');
			return;
		}
		$form.trigger('ListFilterFormUpdate');
	}

	function formsInit() {
		$('.js-listfilter-form').each(function() {
			var $form = $(this);
			if ($form.data('listfilter-initiated')) {
				return;
			}
			var $widget = $('.js-listfilter-widget[data-listfilter-id="'+$(this).data('listfilter-id')+'"]');
			if ($widget.length) {
				$form.data('widget', $widget);
				$widget.data('form', $form);
			}
			// Setup callbacks
			for (var i = 0; i < this.elements.length; ++i) {
				var it = this.elements[i];
				if (it.tagName === 'FIELDSET') {
					continue;
				}
				$(it).change(formFieldChange);
			}
			$form.submit(formSubmit);
			$form.data('listfilter-initiated', true);
			$form.trigger('ListFilterFormInit');
		});
	}

	$('.js-listfilter-widget').bind('ListFilterWidgetInit', function(e) {
		var $form = $(this).data('form');
		if (typeof $form === 'undefined' || !$form || !$form.length) {
			return;
		}
		$form.trigger('ListFilterFormUpdate');
	});

	$('.js-listfilter-form').bind('ListFilterFormUpdate', function(e) {
		var $form = $(this);
		var $widget = $form.data('widget');
		if (typeof $widget === 'undefined' || !$widget || !$widget.length) {
			// debugLog('js-listfilter-form::ListFilterFormUpdate: Missing widget element, ie. element that matches: .js-listfilter[data-listfilter-id="'+$form.data('listfilter-id')+'"]');
			return;
		}
		var allRecords = $widget.data('listfilter-records');
		if (allRecords === false || allRecords === null || typeof allRecords === 'undefined') {
			if (allRecords !== false) {
				debugLog('js-listfilter-form::ListFilterFormUpdate: Missing data-listfilter-records. Widget-specific code like the Google Map should set this data up. Initialize "data-listfilter-records" to false while waiting on AJAX calls.');
			}
			return;
		}
		var backendFilters = $form.data('listfilter-backend');
		var recordsInFilterGroups = {};
		$(this).find('.js-listfilter-filter').each(function() {
			var fieldGroupCallback = $(this).data('fieldgroup-callback');
			if (fieldGroupCallback) {
				var fieldGroupID = $(this).data('fieldgroup-id');
				var backendFilter;
				if (typeof backendFilters[fieldGroupID] !== 'undefined') {
					backendFilter = backendFilters[fieldGroupID];
				} else {
					backendFilter = null;
				}
				var value = $(this).triggerHandler(fieldGroupCallback, [allRecords, backendFilter]);
				if (value) {
					recordsInFilterGroups[fieldGroupID] = value;
				}
			}
		});

		// Show all markers if no filters present
		if ($.isEmptyObject(recordsInFilterGroups)) {
			$widget.trigger('ListFilterRecordsUpdate', [allRecords, true]);
			return;
		}

		// Do 'AND' logic across filter groups. 
		// ie. Ensure a record has tags AND fits into the date range.
		var recordsThatMatchFilter = [];
		for (var fieldGroupID in recordsInFilterGroups) {
			if (!recordsInFilterGroups.hasOwnProperty(fieldGroupID)) {
				continue;
			}
			var recordsMap = recordsInFilterGroups[fieldGroupID];
			for (var recordID in recordsMap) {
				if (!recordsMap.hasOwnProperty(recordID)) {
					continue;
				}
				var isInAllFilters = true;
				for (var otherFieldGroupID in recordsInFilterGroups) {
					if (otherFieldGroupID === fieldGroupID || !recordsInFilterGroups.hasOwnProperty(otherFieldGroupID)) {
						continue;
					}
					var otherFieldGroup = recordsInFilterGroups[otherFieldGroupID];
					if (typeof otherFieldGroup[recordID] === 'undefined') {
						isInAllFilters = false;
						break;
					}
				}
				if (isInAllFilters) {
					var record = recordsMap[recordID];
					recordsThatMatchFilter.push(record);
				}
			}
		}
		// Hide all markers
		$widget.trigger('ListFilterRecordsUpdate', [allRecords, false]);

		// Show markers that match filter criterion
		$widget.trigger('ListFilterRecordsUpdate', [recordsThatMatchFilter, true]);
	});
})(jQuery);