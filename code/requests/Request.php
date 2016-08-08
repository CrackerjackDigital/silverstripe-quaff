<?php
/**
 * Very simple request class which wraps an api.
 */
namespace Quaff\Requests;

use Modular\Object;
use Quaff\Interfaces\Endpoint;
use Quaff\Interfaces\Quaffable;

class Request extends Object {
	/** @var Endpoint */
	protected $endpoint;

	/** @var Quaffable */
	protected $model;

	/** @var array */
	protected $extraData;

	/**
	 * @param Endpoint|string $endpoint e.g. "item/{$id}"
	 * @param Quaffable       $model
	 * @param array           $extraData
	 */
	public function __construct(Endpoint $endpoint, Quaffable $model, array $extraData = array()) {
		parent::__construct();

		$this->endpoint = $endpoint;
		$this->model = $model;
		$this->extraData = $extraData;
	}

	public function getEndpoint() {
		return $this->endpoint;
	}

	public function getModel() {
		return $this->model;
	}

	public function getExtraData() {
		return $this->extraData;
	}
}