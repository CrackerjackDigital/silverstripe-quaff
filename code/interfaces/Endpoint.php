<?php
namespace Quaff\Interfaces;

use Quaff\Responses\Response;

interface Endpoint {

	/**
	 * Prepare the endpoint to make calls if required (e.g. Authenticate).
	 *
	 * @return mixed
	 */
	public function init();

	/**
	 * Calls remote uri and creates models in database.
	 * @return mixed
	 */
	public function sync();

	/**
	 * @param array     $params
	 * @param Quaffable $model
	 * @return Response
	 */
	public function quaff(array $params = [], $model = null);

	public function version();

	public function info($key);

	public function auth();

	/**
	 * Match this endpoints path/info against another endpoint to see if they are the same or
	 * this one handles the one passed (e.g. as a requested endpoint).
	 *
	 * @param $pattern
	 * @param $to
	 * @return mixed
	 */
	public static function match($pattern, $to);

	/**
	 * @param array|null $data
	 * @param null       $flags
	 * @return Model
	 */
	public function modelFactory(array $data = null, $flags = null);

	public function responseFactory($apiData);

	/**
	 * Return the 'info' for the endpoint, generally an array.
	 *
	 * @return mixed
	 */
	public function getInfo();

	/**
	 * Return the path to the item collection in the return data, e.g. 'response.items' or '/response/items' for json and xml respectively.
	 * @return mixed
	 */
	public function getItemPath();

	/**
	 * Return the class name of the 'root' model returned by this endpoint.
	 *
	 * @return mixed
	 */
	public function getModelClass();

	public function getResponseClass();

	public function getErrorClass();

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