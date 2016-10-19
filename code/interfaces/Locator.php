<?php
namespace Quaff\Interfaces;

interface Locator {
	/**
	 * Find an object based on the specs, optionally caching it for later re-retrieval.
	 *
	 * @param  string $alias whatever is needed by the locator to find the target.
	 * @return \Generator yields instance of class being called which matches test criteria
	 */
	public static function locate($alias);

	/**
	 * Test the passed parameter against the instance, e.g. alias is the same
	 * @param string $test
	 * @return bool
	 */
	public function match($test);
}