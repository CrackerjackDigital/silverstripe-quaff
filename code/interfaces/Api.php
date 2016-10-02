<?php
namespace Quaff\Interfaces;



/**
 * Interface used by locator to match and endpoint to a field map for a model.
 *
 * @api
 */
interface Api {

	/**
	 *
	 *
	 * @param array $endpoints paths of endpoints to sync, if not provided then all endpoints for the api will be synced in order
	 *                         defined in config.sync_endpoints
	 * @return \Quaff\Responses\Response
	 */
	public function sync($endpoints = null);

	/**
	 * Return all the endpoints handled by this api.
	 *
	 * @return array
	 */
	public static function endpoints();

	/**
	 * Return a configured endpoint for a path.
	 *
	 * @param $path
	 * @return Endpoint|Object
	 */
	public static function endpoint($path);

	/**
	 * Return a configured endpoint for a model and action.
	 *
	 * @param $modelClass
	 * @param $action
	 * @return Endpoint
	 */
	public function endpointForModel($modelClass, $action);

	/**
	 * Find and return config for an endpoint.
	 *
	 * @param $path
	 * @return bool
	 */
	public function findEndpointConfig($path);

	/**
	 * Create and return an endpoint with provided info..
	 *
	 * @param string $path
	 * @param array  $info
	 * @param bool   $decodeInfo
	 * @return \Quaff\Interfaces\Endpoint
	 */
	public static function make_endpoint($path, array $info, $decodeInfo = true);

}