<?php
/**
 * Provides an api where api calls can be configured and made.
 */
namespace Quaff;

use ClassInfo;
use Director;
use Injector;
use Modular\debugging;
use Modular\Object as Object;
use Quaff\Endpoints\Endpoint;
use Quaff\Exceptions\Exception;
use Quaff\Interfaces\Api as ApiInterface;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Locator as LocatorInterface;

abstract class Api extends Object
	implements ApiInterface, LocatorInterface {
	use debugging;

	/** @var string provide in concrete api implementation as it is the name by which this api can be located by */
	private static $service = '';

	/** @var string use this accept type with requests if none defined in the info */
	private static $accept_type = 'application/json';

	/** @var string the name of a QuaffAuth derived class */
	private static $auth_provider = 'QuaffAuthTypeBasic';

	/** @var string the name of a QuaffTransport derived class */
	private static $transport = 'Quaff\Transport\Guzzle';

	/** @var string set this to log to this file under assets directory, e.g. 'logs/quaff-shuttlerock.log' */
	private static $log_path_name = '';

	/** @var string set this to log errors to this file under assets directory, e.g. 'logs/quaff-shuttlerock-errors.log' */
	private static $error_log_path_name = '';

	/** @var string set this to send error emails to this address when self.error is called */
	private static $error_log_email_address = '';

	private static $sync_endpoints = [
		/*
				'list/some-endpoint' => true
		 */
	];

	private static $endpoints = [ /*
/*   see README endpoint config for more details
       'get/v1/public' => [
           'accept' => 'application/json',
           'url' => 'http://example.com/api/v1/public/',
           'transport' => 'QuaffTransportGuzzle'
       ],
       'private' => [
           'url' => 'https://example.com/api/v1/private/',
           'auth' => [
               'username' => 'fred',
               'password' => 'asdwkdopedopkewpf2u9e903e'
           ]
       ],
       'get/some-endpoint' => [
           'url' => 'some/endpoint',
           'base' => 'get/public',
           'class' => 'some model class',
           'params' => [
				'page' => [ false, '/^\d+$/' ],
				'per_page' => [ false, '/^\d+$/' ]
			]
       ],
       'post/some-private-endpoint' => [
           'url' => 'some/private-endpoint',
           'base' => 'post/private'
           'class' => 'some model class',
			'params' => [
				'id' => [ true, /^[a-z0-9]+$/],
               'title' => [ true ],
               'sku' => [ true, '/^[a-z]{3}-[0-9]{6}$/' ]
			]
       ]
*/
	];

	public function quaff(EndpointInterface $endpoint, array $params = [], $model = null) {
		return $endpoint->quaff($params, $model);
	}

	/**
	 *
	 * Find and return an api implementation for a service.
	 *
	 * @param mixed $service e.g. 'arlo' or 'shuttlerock'
	 * @return Api
	 *
	 */
	public static function locate(EndpointInterface $service) {
		$api = static::cache($service);

		if (!$api) {
			/** @var EndpointInterface $className */
			foreach (ClassInfo::implementorsOf('QuaffApiInterface') as $className) {
				/** @var LocatorInterface $api */
				$api = Injector::inst()->get($className);

				if ($api->match($service)) {
					break;
				}

				$api = null;
			}
		}
		return static::cache($service, $api);
	}

	public static function sync($endpointPaths = null) {
		static::debug_info('Starting sync');

		if (!Director::is_cli()) {
			ob_start('nl2br');
		}

		if (!$endpointPaths) {
			$endpointPaths = static::sync_endpoints();
		}
		if (!is_array($endpointPaths)) {
			$endpointPaths = [$endpointPaths];
		}
		$endpoints = [];
		// first gather all the endpoints passed or from config and assembled the 'active' ones
		foreach ($endpointPaths as $endpointPath => $active) {
			// this could be an array of endpoints to sync or a map of [ endpoint path => truthish to sync ]
			$doSync = is_numeric($endpointPath) ? true : $active;
			$endpointPath = is_numeric($endpointPath) ? $active : $endpointPath;

			if ($doSync) {
				/** @var EndpointInterface $endpoint */
				if ($endpoint = static::endpoint($endpointPath)) {
					$endpoints[ $endpointPath ] = $endpoint;
				}
			}
		}
		if (count($endpoints) != count($endpointPaths)) {
			// die if we couldn't find all the endpoints requested, there may be dependancies
			throw new Exception("Couldn't locate all requested endpoints to sync, aborting sync");
		}
		// now get the endpoints to sync themselves
		/** @var EndpointInterface $endpoint */
		foreach ($endpoints as $endpointPath => $endpoint) {

			$endpoint->sync();
		}
		if (!Director::is_cli()) {
			ob_end_flush();
		}
		static::debug_info('Ending sync');

	}

	/**
	 * @return array either of endpoint paths or endpoint path => sync flag from config.sync_endpoints.
	 */
	protected static function sync_endpoints() {
		return static::config()->get('sync_endpoints');
	}

	/**
	 * Test if this api services a particular endpoint.
	 *
	 * @param $service
	 * @return array|bool info if endpoint is found, otherwise false.
	 */
	public function match(EndpointInterface $service) {
		return $this->service() == $service->getPath();
	}

	/**
	 * Returns config.service
	 *
	 * @return string
	 */
	public function service() {
		return $this->config()->get('service');
	}

	/**
	 * Returns the configured endpoints for this api or empty array.
	 *
	 * @return array
	 */
	public static function endpoints() {
		return static::config()->get('endpoints');
	}

	/**
	 * Return a configured endpoint.
	 *
	 * @param string $path
	 * @return EndpointInterface
	 */
	public static function endpoint($path) {
		foreach (static::endpoints() as $testPath => $config) {
			if (Endpoint::match($testPath, $path)) {
				return static::make_endpoint($path, $config);
			}
		}
		return null;
	}

	/**
	 * Returns an endpoint by model class and action if one can be found.
	 *
	 * @param $modelClass
	 * @param $action
	 * @return null|EndpointInterface
	 * @throws Exception
	 */
	public function endpointForModel($modelClass, $action) {
		$path = "$action/*";

		foreach (static::endpoints() as $testPath => $config) {
			// exclude root endpoints which handle no specific classes
			if (isset($config['class'])) {
				if ($config['class'] == $modelClass) {
					// we have matched by class, now see if the rest of the endpoint path matches
					// using action and wildcard
					if (Endpoint::match($path, $testPath)) {
						return static::make_endpoint($path, $config);
					}
				}
			}
		}
		return null;
	}

	/**
	 * @param $path
	 * @return array|null
	 */
	public function findEndpointConfig($path) {
		foreach (static::endpoints() as $test => $config) {
			if (Endpoint::match($test, $path)) {
				return $config;
			}
		}
		return null;
	}

	/**
	 * Return an endpoint using either $config['endpoint'] or the mangled api class (this) name. Overload in concrete
	 * classes to provide additional initialisation/configuration.
	 *
	 * @param array $path
	 * @param array $config
	 * @param bool  $dereferenceBase if true then base endpoints will also be resolved, otherwise not
	 * @return EndpointInterface
	 * @throws Exception
	 */
	public static function make_endpoint($path, array $config, $dereferenceBase = true) {
		$config = static::decode_config($config, true);

		// TODO: is there a better way to do this rather than a convention?
		$endpointClassName = $config['endpoint']
			? $config['endpoint']
			: str_replace('\\Apis\\', '\\Endpoints\\', get_called_class());

		if (ClassInfo::exists($endpointClassName)) {
			return Injector::inst()->create($endpointClassName, $path, static::decode_config($config, $dereferenceBase));
		}
	}

	/**
	 * Give a map of info decodes it into standard info packet optionally including any base information found.
	 *
	 * @param array $config
	 *
	 * @param bool  $dereferenceBase
	 * @return array
	 */
	protected static function decode_config(array $config, $dereferenceBase = false) {
		$merged = [
			'accept_type' => static::config()->get('accept_type'),
			'url'         => null,
			'base'        => null,
			'transport'   => static::config()->get('transport'),
		];

		if ($dereferenceBase && isset($config['base'])) {

			if ($parentEndpoint = static::endpoint($config['base'])) {

				$baseInfo = $parentEndpoint->getInfo();
				$merged = array_merge(
					$merged,
					$baseInfo
				);

				if (isset($baseInfo['url'])) {
					$config['base'] = $baseInfo['url'];
				}
			}
		}
		$merged = array_merge(
			$merged,
			$config
		);

		return $merged;
	}

	/**
	 * Returns config.platform_name
	 *
	 * @return string
	 */
	public static function platform_name() {
		return static::config()->get('platform_name');
	}

	/**
	 * Returns config.version
	 *
	 * @return string
	 */
	public static function version() {
		return static::config()->get('version');
	}
}