<?php

class ListFilterSetExtension extends Extension {
	private static $has_one = array(
		'ListFilterSet' => 'ListFilterSet',
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->removeByName('ListFilterSetID');
		$filterSetField = DropdownField::create('ListFilterSetID', 'List Filter Set');
		$filterSetField->setEmptyString('(Select a filter set)');
		$filterSetField->setSource(ListFilterSet::get()->map()->toArray());
		if ($fields->dataFieldByName('MenuTitle')) {
			$fields->insertAfter($filterSetField, 'MenuTitle');
		} else {
			$fields->addFieldToTab('Root.Main', $filterSetField);
		}
	}
}