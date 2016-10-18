<?php
namespace Quaff\Endpoints;
/**
 * Endpoint can be used when addressing the local assets folder.
 *
 * @package Quaff\Endpoints
 */
class WebRoot extends Endpoint {
	private static $alias = 'path:webroot';

	private static $meta = [
		'path' => BASE_PATH
	];
}