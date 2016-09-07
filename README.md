Mr Filter
====================================

![mr-filter](https://cloud.githubusercontent.com/assets/3859574/18301899/052886fe-7518-11e6-94ed-24f2758be60a.jpg)

Mr Filter is a filtering form that's configurable in the backend and able to be attached to a Page.

It offers a simple to use API for filtering DataLists in the backend as well as offering flexible front-end filtering logic that is done
without using slow backend responses.

## Caching Example

You can cache the map marker/features by getting a task to occassionally trigger 'updateCacheFile'.

```
<?php
class ListFilterWidgetGoogleMapExtension extends Extension {
	public function updateCacheFile() {
		$features = $this->owner->getFeatureCollection();
		$record = $this->owner->getRecord();
		$name = 'events-markers-'.$record->ID.'.json';
		$featuresAsJSON = json_encode($features);

		$cache = singleton('ListFilterCacheFile');
		$file = $cache->save($featuresAsJSON, 'events-markers-'.$record->ID.'.json');
		return $file;
	}

	public function getCacheFile() {
		$record = $this->owner->getRecord();

		$cache = singleton('ListFilterCacheFile');
		$file = $cache->loadFile('events-markers-'.$record->ID.'.json');
		return $file;
	}

	public function updateDataAttributes(&$parameters) {
		// If cache *.json file exists, use that instead.
		$cacheFile = $this->owner->getCacheFile();
		if ($cacheFile && $cacheFile->exists()) {
			$parameters['features-url'] = $cacheFile->getURL().'?t='.strtotime($cacheFile->LastEdited);
		}
	}
}
```

## Requirements
- SilverStripe 3.1 or higher

## Installation
```composer require silbinarywolf/usersubmissionpages:1.1.*```