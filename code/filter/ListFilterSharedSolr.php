<?php

if (!class_exists('SolrSearchService')) {
	return;
}

class ListFilterSharedSolr extends ListFilterShared {
	/**
	 * @var SolrQueryBuilder
	 */
	protected $builder = null;

	/**
	 * @return SolrQueryBuilder
	 */
	public function getQueryBuilder() {
		if ($this->builder !== null) {
			return $this->builder;
		}
		/**
		 * @var $solr SolrSearchService
		 */
		$solr = singleton('SolrSearchService');
		$builder = $solr->getQueryBuilder();
		return $this->builder = $builder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function applyFilter(SS_List $list) {
		$builder = $this->getQueryBuilder();
		if (!$builder) {
			return;
		}
		$solrClass = $list->dataClass();
		if ($solrClass) {
			$hierarchyClasses = array();
			foreach (ClassInfo::subclassesFor($solrClass) as $class) {
				$hierarchyClasses[] = $class.')';
			}
			$builder->addFilter('(ClassNameHierarchy_ms', implode(' OR (ClassNameHierarchy_ms:', $hierarchyClasses));
		}
		$this->invokeWithExtensions('updateQueryBuilder', $builder);
		/**
		 * @var $solr SolrSearchService
		 */
		$solr = singleton('SolrSearchService');
		$solrResultSet = $solr->query($builder);
		$solrResults = $solrResultSet->getResult();
		if (!isset($solrResults->response->docs)) {
			$errorMessage = __CLASS__.': Missing "SolrResultSet::response->docs" from Solr. Has Solr been started?';
			if (Director::isDev()) {
				throw new Exception($errorMessage);
			} else {
				// Don't completely halt execution if Solr goes down on production.
				user_error($errorMessage, E_USER_WARNING);
				return new ArrayList();
			}
		}
		$ids = array();
		$documents = $solrResults->response->docs;
		foreach ($documents as $document) {
			$id = (int)$document->SS_ID_i;
			$ids[$id] = $id;
		}
		if (!$ids) {
			return new ArrayList();
		}
		$list = $list->filter('ID', $ids);
		return $list;
	}
}