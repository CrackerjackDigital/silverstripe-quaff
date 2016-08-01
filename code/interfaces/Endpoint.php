<?php

interface QuaffEndpointInterface {

	/**
	 * Prepare the endpoint to make calls if required (e.g. Authenticate).
	 *
	 * @return mixed
	 */
	public function init();

	/**
	 * @param array                  $params
	 * @param QuaffMappableInterface $model
	 * @return QuaffAPIResponse
	 */
	public function quaff(array $params = [], $model = null);

	public function version();

	public function info($key);

	public function auth();

	/**
	 * Return the 'info' for the endpoint, generally an array.
	 *
	 * @return mixed
	 */
	public function getInfo();

	/**
	 * Return the class name of the 'root' model returned by this endpoint.
	 *
	 * @return mixed
	 */
	public function getModelClass();

	/**
	 * @return mixed
	 */
	public function getEndpointClass();

	public function getTransportClass();

	public function getBaseURL();

	public function getPath();

	/**
	 * @return string lowercase acceptType e.g. application/json
	 */
	public function getAcceptType();
}