<?php

if (!class_exists('SolrSearchService')) {
	return;
}

class ListFilterSolrFacet extends ListFilterBase {
    
    private static $db = array(
        'FacetOn'       => 'Varchar',
    );
    
    protected $filterList;
    
	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
        $fields = parent::getFilterFields();
        
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
        
        if (!$this->filterList) {
            $this->filterList = CheckboxSetField::create('FacetValues', $this->Title);
        }
        
        $fields->push($this->filterList);
        
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

		if (isset($data['FilterGroup']) && is_array($data['FilterGroup'])) {
            $selected = array_keys($data['FilterGroup']);
            $filter = $this->FacetOn .':"' . implode('" ' . $this->FacetOn .':"', $selected) . '"';
            
            $builder->addFilter($filter);
		}
		
		return $sharedFilter;
	}
    
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
                $this->filterList->setSource($source);
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
//	public function getJavascriptCallback() {
//		return 'ListFilterGroupIDs';
//	}
}