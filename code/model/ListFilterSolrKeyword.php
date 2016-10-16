<?php

// Requires: https://github.com/nyeholt/silverstripe-solr

if (class_exists('SolrSearchService')) {

class ListFilterSolrKeyword extends ListFilterBase {
	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		$fields->push($field = TextField::create('Keywords', 'Keywords'));
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data) {
		if (!isset($data['Keywords']) || !$data['Keywords']) {
			return;
		}
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
		$builder->baseQuery($data['Keywords']);
		return $sharedFilter;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterBackendData(SS_List $list, array $data) {
		if (!isset($data['Keywords']) || !$data['Keywords']) {
			return;
		}
		/**
		 * @var $myFilter ListFilterSharedSolr 
		 */
		$myFilter = $this->LocalFilter('ListFilterSharedSolr'); 
		$builder = $myFilter->getQueryBuilder();
		$builder->baseQuery($data['Keywords']);
		
		$list = $myFilter->applyFilter($list);
		if (!$list) {
			return;
		}
		$ids = array();
		foreach ($list as $record) {
			$ids[$record->ID] = true;
		}
		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getJavascriptCallback() {
		return 'ListFilterGroupIDs';
	}
}

}