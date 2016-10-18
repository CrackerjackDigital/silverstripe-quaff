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
use Quaff\Models\ApiConfig;
use Quaff\Models\EndpointConfig;
use Quaff\Models\SyncLog;

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
	private static $transport = 'Quaff\Transports\Guzzle';

	/** @var string set this to log to this file under assets directory, e.g. 'logs/quaff-shuttlerock.log' */
	private static $log_path_name = '';

	/** @var string set this to log errors to this file under assets directory, e.g. 'logs/quaff-shuttlerock-errors.log' */
	private static $error_log_path_name = '';

	/** @var string set this to send error emails to this address when self.error is called */
	private static $error_log_email_address = '';

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
	 * @return \Generator yields Apis which match requested service (may be more than one)
	 *
	 */
	public static function locate($service) {
		$api = static::cache($service);

		if ($api) {
			yield $api;
		}
		/** @var EndpointInterface $className */
		foreach (ClassInfo::subclassesFor('Quaff\\Api') as $className) {
			if ($className == 'Quaff\\Api') {
				continue;
			}
			/** @var LocatorInterface $api */
			if ($api = Injector::inst()->get($className)) {
				if ($api->match($service)) {
					static::cache($service, $api);
					yield $api;
				}
			}
		}
	}

	/**
	 * Test if this api services a particular endpoint.
	 *
	 * @param string $test alias or service name to match
	 * @return array|bool info if endpoint is found, otherwise false.
	 */
	public function match($test) {
		return $this->service() == $test;
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
	 *
	 * @param array $endpointPaths
	 * @return \Quaff\Responses\Response|void
	 * @throws \Quaff\Exceptions\Exception
	 */
	public static function sync(array $endpointPaths) {

		static::debug_info('Starting sync');

		if (!\Director::is_cli()) {
			ob_start('nl2br');
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
		// now get the found endpoints to sync themselves
		/** @var EndpointInterface $endpoint */
		foreach ($endpoints as $endpointPath => $endpoint) {
			if ($endpoint->sync()) {

			}
		}
		if (!\Director::is_cli()) {
			ob_end_flush();
		}
		static::debug_info('Ending sync');

	}

	/**
	 * Returns the endpoints aliases for this api or empty array.
	 *
	 * @return array
	 */
	public static function endpoints() {
		return static::config()->get('endpoints') ?: [];
	}

	/**
	 * Return a configured endpoint.
	 *
	 * @param string $alias
	 * @return EndpointInterface
	 */
	public static function endpoint($alias, array $moreMeta = []) {
		return Endpoint::locate($alias, $moreMeta);
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