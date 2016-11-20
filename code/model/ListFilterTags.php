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
	 * The tags available to the user on the frontend.
	 *
	 * @return SS_List
	 */
	protected $cache_selected_tags = null;
	final public function SelectableTagsAll() {
		if ($this->cache_selected_tags !== null) {
			return $this->cache_selected_tags;
		}
		$list = $this->SelectableTags();
		$this->extend('updateSelectableTags', $list);
		return $this->cache_selected_tags = $list;
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
	public function getFilterFields() {
		$fields = parent::getFilterFields();
		$tags = $this->SelectableTagsAll();
		if ($tags instanceof SS_List) {
			$tags = $tags->map('ID', 'Title');
		}
		$fields->push($field = CheckboxSetField::create('Tags', '', $tags));
		return $fields;
	}

	/**
	 * Return tag ID(s) used by record
	 *
	 * @return array
	 */
	public function getFilterData(DataObject $record) {
		$relationName = $this->getComponentRelationName();
		$tagsOrHasOne = $record->$relationName();
		$tagIDs = array();
		if ($tagsOrHasOne instanceof DataList || $tagsOrHasOne instanceof ArrayList) {
			// Handle has_many / many_many relationship
			$tagIDs = $tagsOrHasOne->map('ID', 'ID');
			if ($tagIDs instanceof SS_Map) {
				$tagIDs = $tagIDs->toArray();
			}
			$tagIDs = array_values($tagIDs);
		} else if ($tagsOrHasOne && $tagsOrHasOne->exists()) {
			// Handle has_one relationship
			$tagIDs[] = $tagsOrHasOne->ID;
		}
		return array(
			'value' => $tagIDs
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
			$list = $this->SelectableTagsAll();
			if ($list instanceof SS_List) {
				$list = $list->map('Title');
			}
			if ($list instanceof SS_Map) {
				$list = $list->toArray();
			}
			return 'Tags: '.implode(', ', $list);
		}
	}
}