<?php

class ListFilterSolrDateRange extends ListFilterDateRange {
	private static $db = array(
	);


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

	/**
	 * Apply filters to the list based on the date range.
	 *
	 * @return SS_List
	 */
	public function applyDateRange(SS_List $list, $start, $end) {
		// Convert from alternate date format(s)
		if ($start) {
			$start = $this->convertDateFromDateFormat($start);
		}
		if ($end) {
			$end = $this->convertDateFromDateFormat($end);
		}

		$startDateField = $this->StartDateField;
		$endDateField = $this->EndDateField;

		// Apply filter
		// todo(Jake): Use ->filter() where possible as that will work with ArrayList
		if($start && $end) {
			$list = $list->where("
					($startDateField <= '$start' AND $endDateField >= '$end') OR
					($startDateField BETWEEN '$start' AND '$end') OR
					($endDateField BETWEEN '$start' AND '$end')
					");			
		} else if ($start) {
			$list = $list->where("($startDateField >= '$start' OR $endDateField > '$start')");
		} else if ($end) {
			$list = $list->where("($endDateField <= '$end' OR $startDateField < '$end')");
		}
		return $list;
	}

	public function convertDateFromDateFormat($date) {
		$format = $this->getDateFormat();
		if ($format) {
			$dateTime = DateTime::createFromFormat('!'.$this->getDateFormat(), $date);
			if ($dateTime === FALSE) {
				throw new Exception('Failed to create DateTime from "'.$date.'" formatted as "'.$this->getDateFormat().'"');
			}
			return date('Y-m-d', $dateTime->getTimestamp());
		} else {
			return $date;
		}
	}

	/**
	 * Get the date format to use on the fields and show on the frontend.
	 *
	 * @return string
	 */
	public function getDateFormat() {
		$dateFormat = $this->getField('DateFormat');
		if (!$dateFormat) {
			$dateFormat = $this->config()->default_dateformat;
		}
		return $dateFormat;
	}

	/**
	 * Get the date format to use on the fields and show on the frontend.
	 *
	 * @return string
	 */
	public function getDateFormatAsJQuery() {
		$dateFormat = $this->getDateFormat();
		$result = DateField_View_JQuery::convert_iso_to_jquery_format($dateFormat);
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getJavascriptCallback() {
		return 'ListFilterDateRange';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getContext() {
		if ($this->isInDB()) {
			return 'Start Date Field: '.$this->StartDateField.', End Date Field: '.$this->EndDateField;
		}
	}
}