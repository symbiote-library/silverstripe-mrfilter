<?php

// Requires: https://github.com/nyeholt/silverstripe-solr
//			 https://github.com/silverstripe-australia/silverstripe-multivaluefield

if (class_exists('SolrSearchService') && class_exists('MultiValueField')) {

class ListFilterSolrFacet extends ListFilterBase {
	private static $db = array(
		'FacetOn'	=> 'Varchar',
		'FilterMethod'  => 'Varchar',
		'ExcludeFilterCounts' => 'Boolean',
		'DefaultValues'      => 'MultiValueField'
	);
	
	/**
	 * @var CheckboxSetField
	 */
	protected $facetValuesField = null;
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName('FilterMethod');
		$fields->addFieldToTab('Root.Main', DropdownField::create('FilterMethod', 'Filter as', array('or' => 'OR', 'and' => 'AND')));
		
		$fields->dataFieldByName('ExcludeFilterCounts')->setTitle('Exclude this filter from counts of facet results');
		
		return $fields;
	}
	
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
		$connector = ($this->FilterMethod === 'and') ? 'AND' : 'OR';
		
		$facetField = $this->FacetOn;
		$fieldFilterPrefix = '';
		
		// tag if we are going to always output total counts, regardless of whether the field is
		// being filtered on - in other words, facet counts are taken excluding anything tagged
		// https://wiki.apache.org/solr/SimpleFacetParameters
		if ($this->ExcludeFilterCounts && $facetField) {
			$facetField = "{!ex=listfilter$this->ID}$facetField";
			$fieldFilterPrefix = "{!tag=listfilter$this->ID}";
		}
		
		$builder = $sharedFilter->getQueryBuilder();
		if (strlen($facetField)) {
			$builder->addFacetFields(array($this->Title => $facetField));
		}
		
		// Use defaults if defined
		$defaults = $this->DefaultValues->getValues();
		if (is_array($defaults)) {
			$defaults = array_combine($defaults, $defaults); 
		}
		
		// or, if there's something in the request, use that. 
		$filterOn = isset($data['FacetValues']) && is_array($data['FacetValues']) ? $data['FacetValues'] : $defaults;

		if ($filterOn && count($filterOn)) {
			$selected = array_keys($filterOn);
			
			$filter = '(' . $this->FacetOn .':"' . implode('" '. $connector . ' ' . $this->FacetOn .':"', $selected) . '")';
			
			$builder->addFilter($fieldFilterPrefix . $filter);
		}
		
		return $sharedFilter;
	}
	
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
				
				$form = $this->facetValuesField->getForm();
				if (!$form->getHasSubmitted()) {
					// Set default value is no value assigned AND the form hasn't been
					// submitted.
					$vals = $this->DefaultValues->getValues();
					$currentVal = $this->facetValuesField->Value();
					if (count($vals) && !$currentVal) {
						$this->facetValuesField->setValue($vals);
					}
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterBackendData(SS_List $list, array $data) {
		return null;
	}
}

}