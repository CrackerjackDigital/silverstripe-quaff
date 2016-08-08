<?php
namespace Quaff\Tasks;

use BuildTask;
use Config;
use Modular\enabler;
use Quaff\Api;

abstract class SyncTask extends BuildTask {
	use enabler;

	const ApiClass = 'ShuttlerockApi';
	/** overload to provide the name of the endpoint class to sync, or leave blank to call the api sync */
	const EndpointPath = '';

	public function run($request) {
		if ($this->enabled()) {
			/** @var Api $api */
			$api = \Injector::inst()->create(static::ApiClass);
			$api->sync(static::EndpointPath);

		} else {
			die(__CLASS__ . ' is not enabled');
		}
	}

	public function sequence(array $toReorder) {
		return Config::inst()->get(get_called_class(), 'sequence') ?: count($toReorder) + 1;
	}

}