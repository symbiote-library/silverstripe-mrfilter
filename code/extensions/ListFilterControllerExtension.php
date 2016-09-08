<?php

class ListFilterControllerExtension extends Extension {
	private static $allowed_actions = array(
		'ListFilterForm',
	);

	public function ListFilterForm(SS_HTTPRequest $request = null) {
		$page = $this->owner->data();
		$listFilterSet = null;
		if ($page->hasExtension('ListFilterSetExtension')) {
			$listFilterSet = $page->ListFilterSet();
			if (!$listFilterSet->exists()) {
				return '';
			}
		} else {
			throw new Exception('Missing "ListFilterSetExtension" on '.$page->class);
		}
		return ListFilterForm::create($this->owner, __FUNCTION__, $listFilterSet);
	}
}