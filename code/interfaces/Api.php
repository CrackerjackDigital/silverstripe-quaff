<?php
namespace Quaff\Interfaces;

/**
 * Interface used by locator to match and endpoint to a field map for a model.
 *
 * @api
 */
interface Api
{
	
	/**
	 *
	 *
	 * @param string|array $endpointAliases aliases of endpoints to sync, if not provided then all endpoints for the
	 *                                      api will be synced in order defined in config.endpoints
	 * @return \Quaff\Responses\Response
	 */
	public static function sync($endpointAliases = []);
	
	/**
	 * Return all the endpoints handled by this api.
	 *
	 * @return array
	 */
	public static function endpoints();
	
	/**
	 * Return a configured endpoint for a path.
	 *
	 * @param string $alias
	 * @param array  $moreMeta additional meta to pass to endpoint ctor.
	 * @return Endpoint|Object
	 */
	public static function endpoint($alias, array $moreMeta = []);
	
}