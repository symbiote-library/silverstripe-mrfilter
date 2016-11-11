Mr Filter
====================================

![mr-filter](https://cloud.githubusercontent.com/assets/3859574/18301899/052886fe-7518-11e6-94ed-24f2758be60a.jpg)

**WARNING: This module is currently undergoing breaking API changes, use at your own risk.**

Mr Filter is a filtering form that's configurable in the backend and able to be attached to a Page.

It offers a simple to use API for filtering DataLists in the backend as well as offering flexible front-end filtering logic that is done
without using slow backend responses.

## Toggling Map View

Occassionally in your front-end code, you'll want the map to start hidden and be toggled on with
a button. The following code will ensure that if the map starts hidden, that it won't have an incorrect
display once it becomes visible.

```
$('.js-view-map-button').click(function(e) {
	$(this).addClass('is-active');

	$('.js-view-map').removeClass('is-hidden');
	$('.js-view-listing').addClass('is-hidden');
	
	// Fix Google Map display:none; bug
	$('.js-listfilter-widget_googlemap').trigger('GoogleMapRunDrawInit');
});
```

## Requirements
- SilverStripe 3.1 or higher

## Installation
```composer require silbinarywolf/silverstripe-mrfilter:1.0.*```