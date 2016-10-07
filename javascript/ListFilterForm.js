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
		e.preventDefault();
		var $form = $(this);
		if ($form.data('is-loading') === true) {
			return;
		}
		// Update listing areas specifically using the same 'data-listfilter-id'
		var $relatedListing = $('.js-listfilter-listing[data-listfilter-id="'+$form.data('listfilter-id')+'"]');
		// Update all listings in global scope (ie. without a 'data-listfilter-id' value)
		var $globalListing = $('.js-listfilter-listing:not([data-listfilter-id])');

		// Add class to signify its loading
		// todo(Jake): Make state class below configurable
		var loadingClass = 'is-listfilter-loading';
		var loadingElements = [];
		if (loadingClass) {
			loadingElements.push($form);
			loadingElements.push($relatedListing);
			loadingElements.push($globalListing);
		}
		for (var l = 0; l < loadingElements.length; ++l) {
			$(loadingElements[l]).addClass(loadingClass);
		}

		$.support.cors = true;
		$form.trigger('ListFilterAJAXBefore');
		$form.data('is-loading', true);
		$.ajax({
			type: 'GET',
			url: $form.attr('action'),
			crossDomain: true,
			headers: {
				// Force sending of header for IE7/8
				'X-Requested-With': 'XMLHttpRequest'
			},
			data: $form.serialize()
		}).done(function(data, textStatus, jqXHR) {
			$form.data('is-loading', false);
			var template, filterGroups, fields;
			if (typeof data === 'object') {
				filterGroups = data.FilterGroups;
				template = data.Template;
			} else {
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
				$relatedListing.html(template);
				// Update all listings in global scope (ie. without a 'data-listfilter-id' value)
				$globalListing.html(template);
				if ($relatedListing.length + $globalListing.length === 0) {
					debugLog('Missing .js-listfilter-listing element. Unable to put form result anywhere.');
				}
				if (typeof data === 'object') {
					for (var field in data) {
						if (field === 'Template' || field === 'FilterGroups') {
							continue;
						}
						var value = data[field];
						var selector = '.js-listfilter-listing-' + field.toLowerCase();
						var $relatedListingCount = $(selector+'[data-listfilter-id="'+$form.data('listfilter-id')+'"]');
						$relatedListingCount.html(value);
						var $globalListingCount = $(selector+':not([data-listfilter-id])');
						$globalListingCount.html(value);
					}
				}
			}
			if (filterGroups !== null) {
				$form.data('listfilter-backend', filterGroups);
				$form.trigger('ListFilterFormUpdate');
			}
			$form.trigger('ListFilterAJAXDone', [data, textStatus, jqXHR]);
		}).fail(function(x, e, exception) {
			$form.data('is-loading', false);
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
		}).complete(function(dataOrJqXHR, textStatus, jqXHROrErrorThrown) {
			$form.data('is-loading', false);
			for (var l = 0; l < loadingElements.length; ++l) {
				$(loadingElements[l]).removeClass(loadingClass);
			}
			$form.trigger('ListFilterAJAXComplete', [dataOrJqXHR, textStatus, jqXHROrErrorThrown]);
		});
	}

	function formFieldChange(e) {
		var $filterGroupHolder = $(this).parents('.js-listfilter-filter').first();
		var $form = $(this.form);
		if (!$filterGroupHolder.length) {
			debugLog("Missing .js-listfilter-filter as parent element. ListFilterBase_holder must have been modified incorrectly.\n\nThis is also known to occur when CompositeField_holder.ss is put into an /Includes/ folder, rather than /forms/. Old versions of Unclecheese's Display Logic module will do this and break this module.");
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
			if ($form.data('ajax')) {
				$form.submit(formSubmit);
			}
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