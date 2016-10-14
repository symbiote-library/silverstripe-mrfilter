<?php

if (!class_exists('TaxonomyTerm')) {
	return;
}

class ListFilterTaxonomyTerms extends ListFilterTags 
{
    private static $many_many = array(
		'Terms' => 'TaxonomyTerm',
		'TermParents' => 'TaxonomyTerm',
	);

	/**
	 * {@inheritdoc}
	 */
	public function SelectableTags() {
		$result = array();
		foreach ($this->TermParents() as $term) {
			foreach ($term->Children() as $subterm) {
				$result[$subterm->ID] = $subterm;
			}
		}
		foreach ($this->Terms() as $term) {
			$result[$term->ID] = $term;
		}
		return new ArrayList($result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$self = &$this;
		$self->beforeUpdateCMSFields(function($fields) use ($self) {
			$fields->removeByName(array('Terms', 'TermParents'));
			$fields->addFieldToTab('Root.Main', TreeMultiselectField::create('Terms', 'Terms', 'TaxonomyTerm', 'ID', 'Name')
													->setRightTitle('List the selected terms out.'));
			$fields->addFieldToTab('Root.Main', TreeMultiselectField::create('TermParents', 'Term Parents', 'TaxonomyTerm', 'ID', 'Name')
													->setRightTitle('Automatically list all children Taxonomy Terms of the selected terms.'));
		});
		$fields = parent::getCMSFields();
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getComponentRelationName() {
		$componentRelationNames = ListFilterUtility::get_component_names_using_class($this->getListClass(), 'TaxonomyTerm');
		if (count($componentRelationNames) > 1) {
			// todo(Jake): maybe make dropdown to select a specific relation for these cases.
			throw new Exception('Multiple many_many relationships with "TaxonomyTerm"');
		}
		$componentRelationName = reset($componentRelationNames);
		return $componentRelationName;
	}
}
