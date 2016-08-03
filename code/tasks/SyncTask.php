<?php

abstract namespace Quaff;

class SyncTask extends BuildTask {
	const ModelClass = '';

	private static $quaff_task_enabled = true;

	private static $delete_existing = true;

	/**
	 * @param null $enable
	 * @return boolean
	 */
	public static function enabled($enable = null) {
		if ($args = func_get_args()) {
			Config::inst()->update(get_called_class(), 'quaff_task_enabled', $args[0]);
			return static::enabled();
		} else {
			return Config::inst()->get(get_called_class(), 'quaff_task_enabled');
		}
	}

	public function isEnabled() {
		return static::enabled();
	}

	public function sequence(array $toReorder) {
		return Config::inst()->get(get_called_class(), 'sequence') ?: count($toReorder) + 1;
	}

	public function model_class() {
		return static::ModelClass;
	}
}