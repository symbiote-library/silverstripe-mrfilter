<?php

class ListFilterAdmin extends ModelAdmin {
	private static $url_segment = 'listfilter';
	private static $menu_title = 'List Filter Sets';
	private static $managed_models = array('ListFilterSet');
	private static $menu_icon = 'mrfilter/images/funnel_icon.png';
	private static $icon = "event_calendar/images/calendar";
}
