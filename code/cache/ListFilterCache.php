<?php

/**
 * Basic wrapper for simple caching.
 */
class ListFilterCache extends SS_Cache {
	public function save($data, $cacheKey) {
		$cache = $this->factory(__CLASS__);
		return $cache->save($data, $cacheKey);
	}

	public function load($cacheKey) {
		$cache = $this->factory(__CLASS__);
		return $cache->load($cacheKey);
	}
}