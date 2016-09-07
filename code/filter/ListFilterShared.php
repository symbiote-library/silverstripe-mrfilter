<?php

/**
 * A special kind of filter that can be shared and manipulated across
 * multiple MapWidgetFilterGroup objects.
 *
 * The use case is that for example, you might want to make multiple AND queries against
 * Solr effeciently, so to put all Solr-related queries into one query, the Solr-based filter
 * groups can add more filters via a shared filter.
 *
 */
abstract class ListFilterShared extends Object {
	private static $_uid_counter = 0;

	/**
	 * @var int
	 */ 
	public $_uid;

	/** 
	 * @var ListFilterSet
	 */
	protected $record;

	public function __construct(ListFilterSet $record) {
		++self::$_uid_counter;
		$this->_uid = self::$_uid_counter;
		$this->record = $record;
	}

	/**
	 * @return SS_List
	 */
	abstract public function applyFilter(SS_List $list);
}