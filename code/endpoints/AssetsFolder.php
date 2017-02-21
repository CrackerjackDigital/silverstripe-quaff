<?php
namespace Quaff\Endpoints;
/**
 * Endpoint can be used when addressing the local assets folder.
 *
 * @package Quaff\Endpoints
 */
class AssetsFolder extends Endpoint {
	private static $alias = 'path:assets';

	private static $parents = [
		'path:webroot'
	];

	private static $meta = [
		'path' => ASSETS_DIR
	];
}