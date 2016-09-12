<?php

class ListFilterDateRange extends ListFilterBase {
	private static $db = array(
		'StartDateField' => 'Varchar(60)',
		'EndDateField'   => 'Varchar(60)',
		//'DateFormat'	 => 'Varchar',
	);

	private static $defaults = array(
		'Title' 		 => 'Filter by Date',
		'StartDateField' => 'Created',
		'EndDateField'   => 'Created',
	);

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$class = $this->getListClassName();
		$dbFields = array();
		if (class_exists($class)) {
			$dbFields = array_keys($class::config()->db);
			$dbFields = ArrayLib::valuekey($dbFields);
			$dbFields[''] = '(Please select a field)';
			$dbFields['ID'] = 'ID';
			$dbFields['Created'] = 'Created';
			$dbFields['LastEdited'] = 'LastEdited';
		}
		$fields->addFieldToTab('Root.Main', $field = DropdownField::create('StartDateField', 'Start Date Field', $dbFields));
		$fields->addFieldToTab('Root.Main', $field = DropdownField::create('EndDateField', 'End Date Field', $dbFields));
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();

		$fields->push($field = DateField::create('StartDate', 'Start Date'));
		$field->setConfig('dateformat', $this->getDateFormat());
		$field->setConfig('datavalueformat', $this->getDateFormat());
		$field->setAttribute('data-dateformat', $this->getDateFormatAsJQuery());

		$fields->push($field = DateField::create('EndDate', 'End Date'));
		$field->setConfig('dateformat', $this->getDateFormat());
		$field->setConfig('datavalueformat', $this->getDateFormat());
		$field->setAttribute('data-dateformat', $this->getDateFormatAsJQuery());

		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterData(DataObject $record) {
		$startDateField = $this->StartDateField;
		$endDateField = $this->EndDateField;

		$startDate = $record->$startDateField;
		$startDate = explode(' ', $startDate);
		$startDate = $startDate[0];
		$endDate = $record->$endDateField;
		$endDate = explode(' ', $endDate);
		$endDate = $endDate[0];
		return array(
			'value' => array(
				'StartDate' => array($startDate),
				'EndDate' => array($endDate),
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data, $caller) {
		$start = isset($data['StartDate']) ? $data['StartDate'] : null;
		$end = isset($data['EndDate']) ? $data['EndDate'] : null;
		if (!$start && !$end) {
			return;
		}
		
		$list = $this->applyDateRange($list, $start, $end);
		return $list;
	}

	/**
	 * Apply filters to the list based on the date range.
	 *
	 * @return SS_List
	 */
	public function applyDateRange(SS_List $list, $start, $end) {
		// Convert from alternate date format(s)
		if ($start) {
			$start = date('Y-m-d', DateTime::createFromFormat('!'.$this->getDateFormat(), $start)->getTimestamp());
		}
		if ($end) {
			$end = date('Y-m-d', DateTime::createFromFormat('!'.$this->getDateFormat(), $end)->getTimestamp());
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

	public function getDateFormat() {
		// todo(Jake): replace with configurable
		return 'd/m/Y';
		/*$field = new DateField('Temp');
		if ($field) {
			$value = $field->getConfig('dateformat');
			return $value;
		}
		return Config::inst()->get('i18n', 'date_format');*/
	}

	public function getDateFormatAsJQuery() {
		return DateField_View_JQuery::convert_iso_to_jquery_format($this->getDateFormat());
	}

	/**
	 * {@inheritdoc}
	 */
	public function getJavascriptCallback() {
		return 'ListFilterDateRange';
	}
}