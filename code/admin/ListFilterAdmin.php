<?php

class ListFilterAdmin extends ModelAdmin {
	private static $url_segment = 'list-filter-sets';
	private static $menu_title = 'List Filter Sets';
	private static $managed_models = array('ListFilterSet');
}
