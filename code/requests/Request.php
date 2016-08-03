<?php
/**
 * Very simple request class which wraps an api.
 */
namespace Quaff;

use Modular\Object;

class APIRequest extends Object {
	/** @var QuaffEndpointInterface */
	protected $endpoint;

	/** @var QuaffMappableInterface */
	protected $model;

	/** @var array */
	protected $extraData;

	/**
	 * @param QuaffEndpointInterface|string                 $endpoint e.g. "item/{$id}"
	 * @param QuaffMappableExtension|QuaffMappableInterface $model
	 * @param array                                         $extraData
	 */
	public function __construct(QuaffEndpointInterface $endpoint, QuaffMappableInterface $model, array $extraData = array()) {
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