<?php

class QuaffSyncTask extends BuildTask {

	public function isEnabled() {
		return Config::inst()->get(get_called_class(), 'quaff_task_enabled');
	}

	public function sequence(array $toReorder) {
		return Config::inst()->get(get_called_class(), 'sequence') ?: count($toReorder) + 1;
	}

	public function run($request) {
		die("Shouldn't run QuaffSyncTask directly but a class derived from");
	}
}