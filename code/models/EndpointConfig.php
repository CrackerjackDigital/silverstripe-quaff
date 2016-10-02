<?php
namespace Quaff\Models;

use Modular\debugging;
use Modular\Model;
use Quaff\Endpoints\Endpoint;

class EndpointConfig extends Model {
	use debugging;

	private $endpoint;

	private static $db = [
		'Path'           => 'Varchar(32)',
		'ItemPath'       => 'Varchar(64)',
		'ModelClass'     => 'Varchar(255)',
		'ResponseClass'  => 'Varchar(255)',
		'ErrorClass'     => 'Varchar(255)',
		'EndpointClass'  => 'Varchar(255)',
		'TransportClass' => 'Varchar(255)',
		'URL'            => 'Text',
		'BaseURL'        => 'Text',
		'AcceptType'     => 'Varchar(32)'
	];
	private static $info_to_field_map = [
		'path'       => 'Path',
		'itempath'   => 'ItemPath',
		'model'      => 'ModelClass',
		'response'   => 'ResponseClass',
		'error'      => 'ErrorClass',
		'endpoint'   => 'EndpointClass',
		'transport'  => 'TransportClass',
		'url'        => 'URL',
		'baseurl'    => 'BaseURL',
		'accepttype' => 'AcceptType',
	];

	public function fromEndpoint(Endpoint $endpoint, $force = false, $write = true) {
		$this->endpoint = $endpoint;

		if ($this->isInDB() && !$force) {
			$this->debug_fail(new \Modular\Exceptions\Exception("Can't update existing EndpointConfig unless forced"));
		}
		foreach ($this->infoToFieldMap() as $infoName => $fieldName) {
			$this->$fieldName = $endpoint->info($infoName);
		}
		$write && $this->write();
		return $this;
	}

	public function toEndpoint() {
		$className = $this->end
		return $this->endpoint;
	}

	protected function infoToFieldMap() {
		return $this->config()->get('info_to_field_map');
	}
}