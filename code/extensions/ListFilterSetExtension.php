<?php

/**
 * This extension typically goes on the listing page of the DataObject/Page you're showing.
 */
class ListFilterSetExtension extends Extension {
	private static $has_one = array(
		'ListFilterSet' => 'ListFilterSet',
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->removeByName('ListFilterSetID');
		$filterSetField = DropdownField::create('ListFilterSetID', 'List Filter Set', ListFilterSet::get()->map()->toArray());
		$filterSetField->setEmptyString('(Select a filter set)');
		$modelAdmin = singleton('ListFilterAdmin');
		if ($modelAdmin->canView()) {
			$listFilterSet = $this->owner->ListFilterSet();
			$editMessage = 'Manage: ';
			if ($listFilterSet && $listFilterSet->exists()) {
				$editMessage .= '<a href="'.singleton('ListFilterAdmin')->Link().'">View all</a> | <a href="'.$listFilterSet->CMSEditLink().'">Edit this</a>';
			} else {
				$editMessage .= '<a href="'.singleton('ListFilterAdmin')->Link().'">View all</a>';
			}
			$filterSetField->setRightTitle($editMessage);
		}
		if ($fields->dataFieldByName('MenuTitle')) {
			$fields->insertAfter($filterSetField, 'MenuTitle');
		} else {
			$fields->addFieldToTab('Root.Main', $filterSetField);
		}
	}
}