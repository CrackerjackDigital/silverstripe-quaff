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
	 * @param Endpoint      $endpoint
	 * @param array                       $params
	 * @param Mappable|null $model force model if provided, otherwise will be figured out.
	 * @return Response
	 */
	public function quaff(Endpoint $endpoint, array $params = [], $model = null);

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
	 * @return QuaffEndpointInterface|Object
	 */
	public function endpoint($path);

	/**
	 * Return a configured endpoint for a model and action.
	 *
	 * @param $modelClass
	 * @param $action
	 * @return QuaffEndpoint
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
	 * @return QuaffEndpointInterface
	 */
	public function makeEndpoint($path, array $info, $decodeInfo = true);

}