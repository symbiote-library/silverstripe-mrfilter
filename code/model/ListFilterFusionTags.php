<?php

if (!class_exists('FusionTag')) {
	return;
}

class ListFilterFusionTags extends ListFilterTags {
	private static $many_many = array(
		'FusionTags' => 'FusionTag',
	);

	/**
	 * {@inheritdoc}
	 */
	public function SelectableTags() {
		return $this->FusionTags();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName(array('FusionTags'));
		$source = FusionTag::get()->map('ID', 'Title')->toArray();
		$fields->addFieldToTab('Root.Main', ListboxField::create('FusionTags', 'Tags', $source)->setMultiple(true));
		return $fields;
	}

	public function getComponentRelationName() {
		$componentRelationNames = ListFilterUtility::get_component_names_using_class($this->getListClassName(), 'FusionTag');
		if (count($componentRelationNames) > 1) {
			// todo(Jake): maybe make dropdown to select a specific relation for these cases.
			throw new Exception('Multiple many_many relationships with "FusionTag"');
		}
		$componentRelationName = reset($componentRelationNames);
		return $componentRelationName;
	}
}