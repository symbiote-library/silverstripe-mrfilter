<?php

if (class_exists('SolrSearchService')) {

class ListFilterSharedSolr extends ListFilterShared {

	/**
	 * The limit of the number of items we'll care about with a solr based lookup
	 *
	 * @var int
	 */
	private static $max_solr_results = 10000;
    
	/**
	 * @var SolrQueryBuilder
	 */
	protected $builder = null;
	
	/**
	 *
	 * @var SolrResultSet
	 */
	protected $resultSet = null;

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
		return $this->builder = $builder->baseQuery('*');
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
		// Support ListFilterSet::SpecialList type 'Children'
		// ie. Only query children of this parent ID
		$listFilterSet = $this->ListFilterSet();
		if ($listFilterSet->ListClassName === '(Children)') {
			$controller = $listFilterSet->getContentController();
			if ($controller) {
				$dataRecord = $controller->data();
				if ($dataRecord && $dataRecord->exists()) {
					$builder->addFilter('ParentsHierarchy_ms', $dataRecord->ID);
				}
			}
		}
		$this->invokeWithExtensions('updateQueryBuilder', $builder);
		/**
		 * @var $solr SolrSearchService
		 */
		$solr = singleton('SolrSearchService');
		$this->resultSet = $solr->query($builder, 0, $this->config()->max_solr_results);
		$solrResults = $this->resultSet->getResult();
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
		// If the SolrQueryBuilder has sort parameters, then sort the list in the order
		// of the IDs provided.
		$params = $builder->getParams();
		if (isset($params['sort']) && $params['sort']) {
			$table = ClassInfo::baseDataClass($list->dataClass());
            if (Object::has_extension($table, 'Versioned')) {
                $table .= (Versioned::get_reading_mode() == 'Stage.Live') ? '_Live' : '';
            }
			$list = $list->sort(array("FIELD({$table}.ID,".implode(',', $ids).")" => 'ASC'));
		}
		return $list;
	}
	
	/**
	 * @return SolrResultSet
	 */
	public function getResultSet() {
		return $this->resultSet;
	}
}

}