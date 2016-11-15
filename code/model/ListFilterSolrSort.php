<?php

// Requires: https://github.com/nyeholt/silverstripe-solr
//           https://github.com/silverstripe-australia/silverstripe-multivaluefield

if (class_exists('SolrSearchService') && class_exists('MultiValueField')) {

/**
 * A set of fields / values to filter the current request by
 *
 * @author marcus
 */
class ListFilterSolrSort extends ListFilterBase
{
    private static $db = array(
		'SortFieldOptions'	=> 'MultiValueField',
        'SortFieldDefault'	=> 'Varchar',
        'SortDirectionDefault'     => "ENUM('ASC,DESC','DESC')",
	);
    
    private static $defaults = array(
        'SortFieldDefault'  => 'score',
    );
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->replaceField('SortFieldOptions', KeyValueField::create('SortFieldOptions', 'Sort Options')->setRightTitle('FieldName => Field label'));
        
        return $fields;
    }
    
    protected function getFieldOptions() {
        $fields = $this->SortFieldOptions->getValues();
        if (!$fields) {
            $fields = array();
			$fields['score'] = 'Relevance';
        }
        
        return $fields;
    }
    
    public function getFilterFields() {
		$fields = parent::getFilterFields();
        $options = $this->getFieldOptions();
        
		$fields->push(DropdownField::create('SortBy', 'Sort By', $options)->setValue($this->SortFieldDefault));
        $fields->push(DropdownField::create('SortDir', 'Sort order', array('ASC' => 'Ascending', 'DESC' => 'Descending'), $this->SortDirectionDefault));
        
		return $fields;
	}
    
    public function applyFilter(SS_List $list, array $data) {
		$sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
        
        $by = $this->SortFieldDefault;
        $dir = $this->SortDirectionDefault;
        
        if (isset($data['SortDir'])) {
            $dir = $data['SortDir'] == 'ASC' || $data['SortDir'] == 'DESC' ? $data['SortDir'] : $dir;
        }
        
        if (isset($data['SortBy'])) {
            $opts = $this->getFieldOptions();
            $by = isset($opts[$data['SortBy']]) ? $data['SortBy'] : $by;
        }

        if (strpos($by, ',')) {
            $bits = explode(',', $by);
            $by = implode(' ' . $dir . ', ', $bits);
        }

        $builder->sortBy($by, $dir);
		return $sharedFilter;
	}
}

}
