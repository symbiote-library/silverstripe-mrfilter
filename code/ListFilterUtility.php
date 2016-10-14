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

			$class = $list->dataClass();
			list($parentClass, $componentClass, $myIDColumnName, $relationIDColumnName, $manyManyTable) = singleton($class)->manyManyComponent($relationName);
			$list = $list->innerJoin($manyManyTable, "\"{$myIDColumnName}\" = \"$parentClass\".\"ID\" AND \"{$relationIDColumnName}\" IN {$idsSQL}");
		}
		return $list;
	}
}