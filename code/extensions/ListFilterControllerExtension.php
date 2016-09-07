<?php

class ListFilterControllerExtension extends Extension {
	private static $allowed_actions = array(
		'ListFilterForm',
	);

	public function ListFilterForm(SS_HTTPRequest $request = null) {
		$page = $this->owner->data();
		$record = null;
		if ($page->hasExtension('ListFilterSetExtension')) {
			$record = $page->ListFilterSet();
			if (!$record->exists()) {
				return '';
			}
		} else {
			throw new Exception('Missing "ListFilterSetExtension" on '.$page->class);
		}
		return ListFilterForm::create($this->owner, __FUNCTION__, $record);
	}
}