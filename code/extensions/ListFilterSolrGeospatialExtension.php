<?php

/**
 * To be used in conjunction with 'ListFilterSolrGeospatial' filter.
 * This is to be applied on a DataObject with 'Lat' and 'Lng' database fields.
 */
class ListFilterSolrGeospatialExtension extends DataExtension {
	public function updateSolrSearchableFields(&$fields) {
		$fields['LatLng'] = 'LatLng_p';
	}

	public function additionalSolrValues() {
		$latLng = $this->owner->LatLng;
		if ($latLng)
		{
			return array(
				'LatLng' => $latLng,
			);
		}
	}

	public function getLatLng() {
		$lat = $this->owner->Lat;
		$lng = $this->owner->Lng;
		
		if (!$lat || !$lng) {
			return '';
		}
		return $lat.','.$lng;
	}
}
