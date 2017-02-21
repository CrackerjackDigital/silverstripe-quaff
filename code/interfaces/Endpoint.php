<?php
namespace Quaff\Interfaces;

interface Endpoint {

	/**
	 * Prepare the endpoint to make calls if required (e.g. Authenticate).
	 *
	 * @return mixed
	 */
	public function init();

	/**
	 * Calls remote uri and creates models in database.
	 *
	 * @return bool return true if success, false if failed
	 */
	public function sync();

	/**
	 * @param array     $queryParams
	 * @param Quaffable $model
	 * @return Response
	 */
	public function quaff(array $queryParams = [], $model = null);

	public function version();

	public function meta($key);

	public function auth();

	/**
	 * Return the first part of the alies, e.g. 'list' for 'list:entries'
	 * @return mixed
	 */
	public function method();

	/**
	 * Find an existing model of the type this endpoint returns which matches the provided raw api response data.
	 *
	 * @param      $apiData
	 * @param null $flags
	 * @return \Modular\Model|null
	 */
	public function findModel($apiData, $flags = null);

	/**
	 * Create a model from the passed raw api response data.
	 *
	 * @param      $apiData
	 * @param null $flags
	 * @return \Modular\Model
	 */
	public function createEmptyModel($apiData, $flags = null);

	/**
	 * @param $apiData
	 * @return ResponseInterface
	 */
	public function responseFactory($apiData);

	/**
	 * Return the 'info' for the endpoint, generally an array.
	 *
	 * @return mixed
	 */
	public function getMetaData();

	/**
	 * Return the path to the item collection in the return data, e.g. 'response.items' or '/response/items' for json and xml respectively.
	 *
	 * @return mixed
	 */
	public function getItemPath();

	/**
	 * Return the class name of the 'root' model returned by this endpoint.
	 *
	 * @return string
	 */
	public function getModelClass();

	/**
	 * @return string
	 */
	public function getResponseClass();

	/**
	 * @return string
	 */
	public function getErrorClass();

	/**
	 * @return string
	 */
	public function getEndpointClass();

	/**
	 * @return string
	 */
	public function getTransportClass();

	/**
	 * Return a complete built URI for a request
	 *
	 * @param string $action e.g. Transport::ActionRead
	 * @return string
	 */
	public function getURI($action);

	/**
	 * Returns the endpoint 'alias', such as 'list:entries'
	 * @return string
	 */
	public function getAlias();

	/**
	 * @return string lowercase acceptType e.g. application/json
	 */
	public function getAcceptType();
}