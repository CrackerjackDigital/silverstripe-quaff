<?php
namespace Quaff\Models;

use Modular\debugging;
use Modular\Model;
use Quaff\Api;
use Quaff\Endpoints\Endpoint;

class ApiConfig extends Model {
	use debugging;

	private $api;

	private static $db = [
		'AuthProvider' => 'Text',
		'AcceptType'   => 'Varchar(32)',
		'PlatformName' => 'Varchar(255)',
		'Version'      => 'Varchar(8)',
	];
	private static $info_to_field_map = [
		'auth_provider' => 'AuthProvider',
		'transport'     => 'Transport',
		'platform_name' => 'PlatformName',
		'accepttype'    => 'AcceptType',
	];

	public function fromApi(Api $api, $force = false, $write = true) {
		$this->api = $api;

		if ($this->isInDB() && !$force) {
			$this->debug_fail(new \Modular\Exceptions\Exception("Can't update existing ApiConfig unless forced"));
		}
		$config = $api->config();

		foreach ($this->infoToFieldMap() as $infoName => $fieldName) {
			$this->$fieldName = $config->get($infoName);
		}
		$write && $this->write();
		return $this;
	}

	public function api() {
		return $this->api;
	}

	protected function infoToFieldMap() {
		return $this->config()->get('info_to_field_map');
	}
}