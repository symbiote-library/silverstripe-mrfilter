<?php

class ListFilterUtility {
	const MODULE_DIR = 'mrfilter';

	/**
	 * @return array
	 */
	public static function get_templates($templateName, $recordOrClasses) {
		// Determine class post-fixes
		$classes = array();
		if (is_object($recordOrClasses)) {
			foreach (array_reverse(ClassInfo::ancestry($recordOrClasses)) as $class) {
				if ($class === 'DataObject') {
					break;
				}
				$classes[] = $class;
			}
		} else if (is_array($recordOrClasses)) {
			$classes[] = $recordOrClasses;
		} else if (is_string($recordOrClasses)) {
			$classes[] = $recordOrClasses;
		}

		// Setup templates
		$result = array();
		foreach ($classes as $class) {
			// ie. 'ListFilterListing_Calendar'
			$result[] = $templateName.'_'.$class;
		}
		// ie. 'ListFilterListing'
		$result[] = $templateName;
		return $result;
	}

	/**
	 * @return string
	 */
	public static function get_relation_type($class, $relationName) {
		$config = $class::config();
		if (isset($config->has_one[$relationName])) {
			return 'has_one';
		}
		if (isset($config->has_many[$relationName])) {
			return 'has_many';
		}
		if (isset($config->many_many[$relationName])) {
			return 'many_many';
		}
		return null;
	}

	/**
	 * @return string
	 */
	public static function get_component_names_using_class($class, $relationClass) {
		$result = array();
		$manyMany = $class::config()->many_many;
		$componentRelationName = null;
		foreach ($manyMany as $relationName => $className) {
			if ($className === $relationClass) {
				//if ($componentRelationName !== null) { throw new Exception('Multiple many_many relationships with "FusionTag"'); }
				$result[] = $relationName;
			}
		}
		return $result;
	}

	/**
	 * @return DataList
	 */
	public static function filter_by_relation_ids(SS_List $list, $relationName, array $ids) {
		foreach ($ids as $k => $v) {
			$ids[$k] = (int)$v;
		}

		$class = $list->dataClass();
		$relationType = self::get_relation_type($class, $relationName);
		switch ($relationType) {
			case 'has_one':
				$list = $list->filter(array($relationName.'ID' => $ids));
			break;

			case 'many_many':
				if ($list instanceof ArrayList) {
					$result = array();
					foreach ($list as $record) {
						$subList = $record->$relationName();
						if ($subList && $subList->find('ID', $ids)) {
							$result[] = $record;
						}
					}
					$list = new ArrayList($result);
				} else {
					$idsSQL = "(".implode(',', $ids).")";
					$classObj = singleton($class);
					$manyManyInfo = $classObj->hasMethod('manyManyComponent') ? 'manyManyComponent' : 'many_many';
					list($parentClass, $componentClass, $myIDColumnName, $relationIDColumnName, $manyManyTable) = $classObj->$manyManyInfo($relationName);
					$list = $list->innerJoin($manyManyTable, "\"{$myIDColumnName}\" = \"$parentClass\".\"ID\" AND \"{$relationIDColumnName}\" IN {$idsSQL}");
				}
			break;

			default:
				throw new Exception('Relation type "'.$relationType.'" is not supported by '.__CLASS__.'::'.__FUNCTION__.'()');
			break;
		}
		return $list;
	}
}