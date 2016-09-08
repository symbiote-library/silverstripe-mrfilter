<?php

class ListFilterTags extends ListFilterBase {
	/**
	 * Hide from 'GridFieldAddClassesButton'
	 *
	 * @var string
	 */
	private static $hide_ancestor = 'ListFilterTags';

	/**
	 * The tags available to the user on the frontend.
	 *
	 * @return SS_List
	 */
	public function SelectableTags() {
		throw new Exception($this->class.' must override '.__FUNCTION__);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getComponentRelationName() {
		throw new Exception($this->class.' must override '.__FUNCTION__);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		$tags = $this->SelectableTags();
		if ($tags instanceof SS_List) {
			$tags = $tags->map('ID', 'Title');
		}
		$fields->push($field = CheckboxSetField::create('Tags', $this->Title, $tags));
		return $fields;
	}

	/**
	 * Return tag IDs used by record
	 *
	 * @return array
	 */
	public function getFilterData(DataObject $record) {
		$relationName = $this->getComponentRelationName();
		$tags = $record->$relationName();
		return array(
			'value' => array_values($tags->map('ID', 'ID')->toArray())
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list, array $data) {
		if (!isset($data['Tags'])) {
			return;
		}
		$list = ListFilterUtility::filter_by_relation_ids($list, $this->getComponentRelationName(), array_keys($data['Tags']));
		return $list;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getJavascriptCallback() {
		return 'ListFilterTags';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getContext() {
		if ($this->isInDB()) {
			$list = $this->SelectableTags();
			if ($list instanceof SS_List) {
				$list = $list->map('Title');
			}
			return 'Tags: '.implode(', ', $list);
		}
	}
}