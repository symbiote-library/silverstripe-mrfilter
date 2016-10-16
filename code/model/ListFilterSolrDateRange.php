<?php

// Requires: https://github.com/nyeholt/silverstripe-solr

if (class_exists('SolrSearchService')) {

class ListFilterSolrDateRange extends ListFilterDateRange {
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('EndDateField');
        return $fields;
    }


	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data) {
        $start = isset($data['StartDate']) ? $data['StartDate'] : null;
		$end = isset($data['EndDate']) ? $data['EndDate'] : null;
        
		if (!$start && !$end) {
			return;
		}
		
        $sharedFilter = $this->SharedFilter('ListFilterSharedSolr');
		$builder = $sharedFilter->getQueryBuilder();
        
        $startDateField = $this->StartDateField == 'LastEdited' ? 'last_modified' : $this->StartDateField . '_dt';
        
        $startDate = '1950-01-01T00:00:00Z';
        $endDate = '2050-01-01T00:00:00Z';

        $fmt = i18n::config()->date_format;
        if (!$fmt) {
            $fmt = 'yyyy-MM-d';
        }
        
		if ($start) {
            $dt = new Zend_Date($start, $fmt, i18n::get_locale());
            $local = strtotime(date('Y-m-d 00:00:00', $dt->getTimestamp()));
            $startDate = gmdate('Y-m-d\TH:i:s\Z', $local);
        }
        if ($end) {
            $dt = new Zend_Date($end, $fmt, i18n::get_locale());
            $local = strtotime(date('Y-m-d 23:59:59', $dt->getTimestamp()));
            $endDate = gmdate('Y-m-d\TH:i:s\Z', $local);
        }

        $builder->addFilter("$startDateField:[$startDate TO $endDate]");
		return $sharedFilter;
	}
}

}