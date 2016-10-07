<?php

if (!class_exists('SolrSearchService')) {
	return;
}

class ListFilterSolrFacet extends ListFilterBase {
	private static $db = array(
		'FacetOn'	=> 'Varchar',
	);
	
	/**
	 * @var CheckboxSetField
	 */
	protected $facetValuesField = null;
	
	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
		
		if (!$this->facetValuesField) {
			$this->facetValuesField = CheckboxSetField::create('FacetValues', $this->Title);
		}
		
		$fields->push($this->facetValuesField);
		
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data) {
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
		if (strlen($this->FacetOn)) {
			$builder->addFacetFields(array($this->FacetOn));
		}

		if (isset($data['FacetValues']) && is_array($data['FacetValues'])) {
			$selected = array_keys($data['FacetValues']);
			$filter = $this->FacetOn .':"' . implode('" ' . $this->FacetOn .':"', $selected) . '"';
			
			$builder->addFilter($filter);
		}
		
		return $sharedFilter;
	}

	/**
	 * {@inheritdoc}
	 */
	// todo(Jake): Copy behaviour from ListFilterSolrKeyword
	/*public function getFilterBackendData(SS_List $list, array $data) {

	}*/
	
	/**
	 * {@inheritdoc}
	 */
	public function finaliseFilter(SS_List $list) {
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$result = $sharedFilter->getResultSet();
		if ($result) {
			$facets = $result->getFacets();
			if (isset($facets[$this->FacetOn])) {
				$source = array();
				foreach ($facets[$this->FacetOn] as $facet) {
					$source[$facet->Name] = $facet->Name . ' (' . $facet->Count . ')';
				}
				// NOTE(Jake): Update facets field after the Solr query has been executed as the query results
				// 			   contain the available facets.
				$this->facetValuesField->setSource($source);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterBackendData(SS_List $list, array $data) {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	// todo(Jake): Copy behaviour from ListFilterSolrKeyword
	/*public function getJavascriptCallback() {
		return 'ListFilterGroupIDs';
	}*/
}