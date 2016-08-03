<?php
namespace Quaff;

/**
 * Extension to add 'helper' methods which can be called when mapping values. Methods should be
 * declared as protected on the concrete class (or public if they are useful as an extension method in general),
 * and are called if a field map has a spec of e.g. 'Name.URLSegment()'.
 */
abstract class MapHelper extends ModelExtension {
	public function quaffMap($method, $value, array $fieldInfo = [], &$internallyHandled = false) {
		// check if concrete class implements the method and call it.
		if (method_exists($this, $method)) {
			return $this->$method($value, $fieldInfo, $internallyHandled);
		}
		// allow a pre-existing method with no parameters to be called
		if ($this->owner->hasMethod($method)) {
			return $this->owner->$method();
		}
	}
}