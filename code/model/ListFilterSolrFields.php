<?php

// Requires: https://github.com/nyeholt/silverstripe-solr
//           https://github.com/silverstripe-australia/silverstripe-multivaluefield

if (class_exists('SolrSearchService') && class_exists('MultiValueField')) {

/**
 * A set of fields / values to filter the current request by
 *
 * @author marcus
 */
class ListFilterSolrFields extends ListFilterBase
{
    const BOOST_MAX = 10;
    
    private static $db = array(
		'SolrFilterFields'	=> 'MultiValueField',
        'BoostMatchFields' => 'MultiValueField',
	);
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->replaceField('SolrFilterFields', 
            KeyValueField::create('SolrFilterFields')
                ->setRightTitle('Solr fields to filter by; use $FieldName to take a property from the "current" page')
        );
        
        $boostVals = array();
        for ($i = 1; $i <= static::BOOST_MAX; $i++) {
            $boostVals[$i] = $i;
        }

        $fields->replaceField(
            'BoostMatchFields',
            KeyValueField::create('BoostMatchFields', 'Boost fields with field/value matches', array(), $boostVals)
                ->setRightTitle("Field_name:matchvalue on the left, boost number on the right")
        );

        return $fields;
    }
    
    public function applyFilter(SS_List $list, array $data) {
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
        
        $filters = $this->SolrFilterFields->getValues();
        if (count($filters)) {
            foreach ($filters as $field => $value) {
                if ($value{0} == '$') {
                    $curr = Controller::has_curr() ? Controller::curr() : null;
                    if ($curr instanceof ContentController) {
                        $page = $curr->data();
                        if ($page) {
                            $keyword = substr($value, 1);
                            $value = $page->hasField($keyword) ? $page->$keyword : $value;
                        }
                    }
                }
                $builder->addFilter($field, $value);
            }
        }
        
        if ($boost = $this->BoostMatchFields->getValues()) {
            if (count($boost)) {
                $builder->boostFieldValues($boost);
            }
        }
		
		return $sharedFilter;
	}
} 

}