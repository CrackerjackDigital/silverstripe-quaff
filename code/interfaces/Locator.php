<?php
namespace Quaff\Interfaces;

interface Locator {
	/**
	 * Find an object based on the specs, optionally caching it for later re-retrieval.
	 *
	 * @param  mixed $spec whatever is needed by the locator to find the target.
	 * @return Object
	 */
	public static function locate($spec);

	public function match($spec);
}