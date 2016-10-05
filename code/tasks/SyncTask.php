<?php
namespace Quaff\Tasks;

use BuildTask;
use Modular\config;
use Modular\Debugger;
use Modular\debugging;
use Modular\enabler;
use Quaff\Api;
use Quaff\Exceptions\Exception;
use Quaff\Interfaces\Endpoint as EndpointInterface;

abstract class SyncTask extends BuildTask {
	use config;
	use enabler;
	use debugging;

	// name of Api service as Injector see's it, e.g 'shuttlerock'
	const ApiServiceAlias = '';

	// aliases of endpoints to sync on the Api service
	private static $endpoints = [
		# e.g:
		# 'sync:entries',
	    # 'import:sitetree'
	];

	// set to SS_Log::INFO = 6 for logging full information, SS_Log::ERR = 3 for just errors.
	private static $log_level = \SS_Log::INFO;

	private static $log_email = '';

	private static $log_file = '';

	/**
	 * Override to use enabler.enabled instead of BuildTask.
	 * @return bool
	 */
	public function isEnabled() {
		return $this->enabled();
	}

	/**
	 * Iterate through config.endpoints, find the endpoint on the service and call sync on the Api for that endpoint.
	 * @param $request
	 * @throws \Quaff\Exceptions\Exception
	 */
	public function run($request) {
		$this->debugger($this->config()->get('log_level'))
			->toFile(Debugger::DebugInfo, $this->config()->get('log_file'))
			->toScreen(Debugger::DebugAll)
			->sendFile($this->config()->get('log_email'));

		if ($this->enabled()) {
			/** @var Api $api */
			foreach (Api::locate(static::ApiServiceAlias) as $api) {
				$api::sync($this->config()->get('endpoints'));
			}


		} else {
			$this->debug_warn('disabled');
		}
	}


}