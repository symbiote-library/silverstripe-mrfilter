<?php

if (!class_exists('TaxonomyTerm')) {
	return;
}

/**
 * @author marcus
 */
class ListFilterTaxonomyTerms extends ListFilterTags 
{
    private static $many_many = array(
		'Terms' => 'TaxonomyTerm',
	);

	/**
	 * {@inheritdoc}
	 */
	public function SelectableTags() {
		return $this->Terms();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName(array('Terms'));
        $source = TreeMultiselectField::create('Terms', 'Terms to filter on', 'TaxonomyTerm', 'ID', 'Name');
		$fields->addFieldToTab('Root.Main', $source);
		return $fields;
	}

	public function getComponentRelationName() {
		$componentRelationNames = ListFilterUtility::get_component_names_using_class($this->getListClassName(), 'TaxonomyTerm');
		if (count($componentRelationNames) > 1) {
			// todo(Jake): maybe make dropdown to select a specific relation for these cases.
			throw new Exception('Multiple many_many relationships with "FusionTag"');
		}
		$componentRelationName = reset($componentRelationNames);
		return $componentRelationName;
	}
}
