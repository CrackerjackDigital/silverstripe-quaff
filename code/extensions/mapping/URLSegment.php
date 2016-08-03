<?php

/**
 * Adds a 'URLSegment' method to extended object
 */
namespace Quaff;

class URLSegmentMapHelper extends MapHelper {
	/**
	 * Filter method called by traverse and build.
	 *
	 * @param       $value
	 * @param array $fieldInfo
	 * @param bool  $internallyHandled
	 * @return string
	 */
	protected function URLSegment($value, array $fieldInfo = [], &$internallyHandled = false) {
		return Injector::inst()->get('URLSegmentFilter')->filter($value);
	}
}
