<?php

if (!class_exists('CalendarDateTime')) {
	return;
}

class ListFilterCalendarEventDateRange extends ListFilterDateRange {
	/**
	 * Use 'StartDate' from CalendarDateTime class.
	 *
	 * @return string
	 */
	public function getStartDateField() {
		return 'StartDate';
	}

	/**
	 * Use 'EndDate' from CalendarDateTime class.
	 *
	 * @return string
	 */
	public function getEndDateField() {
		return 'EndDate';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		foreach (array('StartDateField', 'EndDateField') as $fieldName) {
			$field = $fields->dataFieldByName($fieldName);
			if (!$field) {
				continue;
			}
			$field = $field->performReadonlyTransformation();
			$fields->replaceField($field->getName(), $field);
		}
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		$startDateField = $fields->dataFieldByName('StartDate');
		if ($startDateField) {
			// Set startDateField to default on the current date.
			// todo(Jake): Move this to ListFilterDateRange if it makes sense to belong there.
			$startDateField->setValue(date($this->getDateFormat()));
		}
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterData(DataObject $record) {
		$startDate = array();
		$endDate = array();
		foreach ($record->DateTimes() as $dateTime) {
			$startDate[] = $dateTime->StartDate;
			$endDate[] = $dateTime->EndDate;
		}
		return array(
			'value' => array(
				'StartDate' => $startDate,
				'EndDate' => $endDate,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data) {
		$start = isset($data['StartDate']) ? $data['StartDate'] : null;
		$end = isset($data['EndDate']) ? $data['EndDate'] : null;

		$calendarDateTimeList = CalendarDateTime::get();
		if (!$start && !$end) {
			$startDateField = $this->getStartDateField();
			$calendarDateTimeList = $calendarDateTimeList->where("{$startDateField} >= DATE(NOW())");
		} else {
			$calendarDateTimeList = $this->applyDateRange($calendarDateTimeList, $start, $end);
		}
		$calendarDateTimeList = $calendarDateTimeList->sort(array(
			'StartDate' => 'ASC', 
			'StartTime' => 'ASC',
			// NOTE(Jake): If showing user the range of dates, ie. "From 3rd August to 5th September", you want events
			// 			   that are "From 3rd August to 1st September" to show before "From 3rd August to 5th September" events.
			'EndDate' => 'ASC',
			'EndTime' => 'ASC',
		));

		$dateTimeIDs = $calendarDateTimeList->column('EventID');
		$dateTimeIDs = $dateTimeIDs ? array_combine($dateTimeIDs, $dateTimeIDs) : array();
		if (!$dateTimeIDs) {
			return new ArrayList();
		}
		$list = $list->filter(array('ID' => $dateTimeIDs));

		// Sort events by closest to furthest away
		$table = 'CalendarEvent';
		$table .= (Versioned::get_reading_mode() == 'Stage.Live') ? '_Live' : '';
		$list = $list->sort(array("FIELD({$table}.ID,".implode(',', $dateTimeIDs).")" => 'ASC'));
		return $list;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfigError() {
		$class = $this->getListClassName();
		if ($this->isInDB() && !is_a($class, 'CalendarEvent', true)) {
			return 'Incorrectly configured. List Type must be "CalendarEvent" or subclass.';
		}
	}
}