<?php
use Modular\ModelExtension;

class QuaffModelControllerExtension extends ModelExtension {

	/**
	 * Get extra statics from other extensions on owner via quaffStatics extension method and calling quaffStatic
	 * directly on owner if it exists. Extensions take precedence over the direct call.
	 *
	 * @param string $class
	 * @param string $extension
	 * @return array
	 */
	public function extraStatics($class = null, $extension = null) {
		/** @var Object $owner */
		$owner = $this();

		$direct = method_exists($owner, 'quaffStatics')
			? $owner->quaffStatics() ?: []
			: [];

		$extended = array_merge_recursive($owner->extend('quaffStatics') ?: []);

		return array_merge(
			parent::extraStatics($class, $extension) ?: [],
			$direct,
			$extended
		);
	}
}
