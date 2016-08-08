<?php
namespace Quaff\Extensions\Model;

/**
 * Adds a 'URLSegment' method to extended object
 */
use Injector;

class URLSegment extends Model {
	public function quaffMap($method, $value, array $fieldInfo = [], &$internallyHandled = false) {
		// check if concrete class implements the method and call it.
		if (method_exists($this, $method)) {
			return $this->$method($value, $fieldInfo, $internallyHandled);
		}
		// allow a pre-existing method with no parameters to be called
		if ($this->owner->hasMethod($method)) {
			return $this->owner->$method();
		}
		return [];
	}

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
