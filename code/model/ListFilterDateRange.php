<?php

class ListFilterDateRange extends ListFilterBase {
	private static $db = array(
		'StartDateField' => 'Varchar(60)',
		'EndDateField'   => 'Varchar(60)',
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
		$self = &$this;
		$self->beforeUpdateCMSFields(function($fields) use ($self) {
			$class = $self->getListClass();
			$dbFields = array(
				'' => '(Please select a field)',
				'ID' => 'ID',
				'Created' => 'Created',
				'LastEdited' => 'LastEdited',
			);
			if ($class && class_exists($class)) {
				$classDbFields = array_keys($class::config()->db);
				$classDbFields = ArrayLib::valuekey($classDbFields);
				$dbFields = array_merge($dbFields, $classDbFields);
			}
			$fields->addFieldToTab('Root.Main', $field = DropdownField::create('StartDateField', 'Start Date Field', $dbFields));
			$fields->addFieldToTab('Root.Main', $field = DropdownField::create('EndDateField', 'End Date Field', $dbFields));
		});
		$fields = parent::getCMSFields();
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		$fields->push(DateField::create('StartDate', 'Start Date'));
		$fields->push(DateField::create('EndDate', 'End Date'));
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
	public function applyFilter(SS_List $list, array $data) {
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
		$startDateField = $this->StartDateField;
		$endDateField = $this->EndDateField;

		// Use DateField to convert from the date format to 'yyyy-mm-dd' for
		// database use.
		$dateField = DBField::create_field('DateField', $start);
		$start = $dateField->dataValue();
		$dateField->setValue($end);
		$end = $dateField->dataValue();

		// Default blank date to anything in the future
		if (!$start) {
			$start = date('Y-m-d');
		}

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
			$result = array();
			if ($value = $this->StartDateField) {
				$result[] = 'Start Date Field: '.$value;
			}
			if ($value = $this->EndDateField) {
				$result[] = 'End Date Field: '.$value;
			}
			return implode(',', $result);
		}
	}
}