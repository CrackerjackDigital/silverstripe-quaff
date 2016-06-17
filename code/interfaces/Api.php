<?php

/**
 * Interface used by locator to match and endpoint to a field map for a model.
 *
 * @api
 */
interface QuaffApiInterface {

	/**
	 *
	 *
	 * @param QuaffEndpointInterface      $endpoint
	 * @param array                       $params
	 * @param QuaffMappableInterface|null $model force model if provided, otherwise will be figured out.
	 * @return QuaffAPIResponse
	 */
	public function quaff(QuaffEndpointInterface $endpoint, array $params = [], $model = null);

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