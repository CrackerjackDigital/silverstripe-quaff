<?php
namespace Quaff\Endpoints;

/**
 * Endpoint can be used to address the local web server
 */
class WebSite extends Endpoint {
	private static $alias = 'url:webroot';

	private static $meta = [
		'url' => BASE_URL
	];
}