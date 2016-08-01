<?php
use \Modular\ModularObject as Object;

/**
 * Provides an api where api calls can be configured and made.
 */
class QuaffApi extends Object
	implements QuaffApiInterface, QuaffLocatorInterface {
	/** @var string use this accept type with requests if none defined in the info */
	private static $accept_type = 'application/json';

	/** @var string the name of a QuaffAuth derived class */
	private static $auth_provider = 'QuaffAuthTypeBasic';

	/** @var string the name of a QuaffTransport derived class */
	private static $transport = 'QuaffTransportGuzzle';

	/** @var string set this to log to this file under assets directory, e.g. 'logs/quaff-shuttlerock.log' */
	private static $log_path_name = '';

	/** @var string set this to log errors to this file under assets directory, e.g. 'logs/quaff-shuttlerock-errors.log' */
	private static $errorlog_path_name = '';

	/** @var string set this to send error emails to this address when self.error is called */
	private static $errorlog_email_address = '';

	/** @var string provide in concrete api implementation as it is the name by which this api can be located by */
	private static $service = '';

	private static $endpoints = [
#   see README endpoint config for more details
#       'get/v1/public' => [
#           'accept' => 'application/json',
#           'url' => 'http://example.com/api/v1/public/',
#           'transport' => 'QuaffTransportGuzzle'
#       ],
#       '*/private' => [
#           'url' => 'https://example.com/api/v1/private/',
#           'auth' => [
#               'username' => 'fred',
#               'password' => 'asdwkdopedopkewpf2u9e903e'
#           ]
#       ],
#       'get/some-endpoint' => [
#           'url' => 'some/endpoint',
#           'base' => 'get/public',
#           'class' => 'some model class',
#           'params' => [
#				'page' => [ false, '/^\d+$/' ],
#				'per_page' => [ false, '/^\d+$/' ]
#			]
#       ],
#       'post/some-private-endpoint' => [
#           'url' => 'some/private-endpoint',
#           'base' => 'post/private'
#           'class' => 'some model class',
#			'params' => [
#				'id' => [ true, /^[a-z0-9]+$/],
#               'title' => [ true ],
#               'sku' => [ true, '/^[a-z]{3}-[0-9]{6}$/' ]
#			]
#       ]
	];

	public function quaff(QuaffEndpointInterface $endpoint, array $params = [], $model = null) {
		return $endpoint->quaff($params, $model);
	}

	/**
	 *
	 * Find and return an api implementation for a service.
	 *
	 * @param mixed $service e.g. 'arlo' or 'shuttlerock'
	 * @return \QuaffApiInterface
	 *
	 */
	public static function locate($service) {
		$api = static::cache($service);

		if (!$api) {
			/** @var QuaffEndpointInterface $className */
			foreach (ClassInfo::implementorsOf('QuaffApiInterface') as $className) {
				/** @var QuaffLocatorInterface $api */
				$api = Injector::inst()->get($className);

				if ($api->match($service)) {
					break;
				}

				$api = null;
			}
		}
		return static::cache($service, $api);
	}

	/**
	 * Test if this api services a particular endpoint.
	 *
	 * @param $service
	 * @return array|bool info if endpoint is found, otherwise false.
	 */
	public function match($service) {
		return $this->service() == $service;
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
	 * @param $path
	 * @return QuaffEndpointInterface
	 */
	public function endpoint($path) {
		foreach (static::endpoints() as $testPath => $config) {
			if (QuaffEndpoint::match($testPath, $path)) {
				return $this->makeEndpoint($path, $config);
			}
		}
		return null;
	}

	/**
	 * Returns an endpoint by model class and action if one can be found.
	 *
	 * @param $modelClass
	 * @param $action
	 * @return null|\QuaffEndpointInterface
	 * @throws \QuaffException
	 */
	public function endpointForModel($modelClass, $action) {
		$path = "$action/*";

		foreach (static::endpoints() as $testPath => $config) {
			// exclude root endpoints which handle no specific classes
			if (isset($config['class'])) {
				if ($config['class'] == $modelClass) {
					// we have matched by class, now see if the rest of the endpoint path matches
					// using action and wildcard
					if (QuaffEndpoint::match($path, $testPath)) {
						return $this->makeEndpoint($path, $config);
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
			if (QuaffEndpoint::match($test, $path)) {
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
	 * @param bool  $decodeInfo if true then base endpoints will also be resolved, otherwise not
	 * @return QuaffEndpointInterface
	 * @throws QuaffException
	 */
	public function makeEndpoint($path, array $config, $decodeInfo = true) {
		$config = $this->decode_config($config, true);

		$endpointClassName = isset($config['endpoint'])
			? $config['endpoint']
			: substr(get_class($this), 0, -3) . 'Endpoint';

		if (!ClassInfo::exists($endpointClassName)) {
			// TODO handle endpoint not existing, maybe work with QuaffEndpoint
			throw new QuaffException("Endpoint class '$endpointClassName' doesn't exist");
		}

		return Injector::inst()->create($endpointClassName, $path, $this->decode_config($config, $decodeInfo));
	}

	/**
	 * Give a map of info decodes it into standard info packet optionally including any base information found.
	 *
	 * @param array $config
	 *
	 * @param bool  $dereferenceBase
	 * @return array
	 */
	protected function decode_config(array $config, $dereferenceBase = false) {
		$merged = [
			'accept_type' => static::config()->get('accept_type'),
			'url'         => null,
			'base'        => null,
			'transport'   => static::config()->get('transport'),
		];

		if ($dereferenceBase && isset($config['base'])) {

			if ($endpoint = $this->endpoint($config['base'])) {

				$baseInfo = $endpoint->getInfo();
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
	 * Writes to config.errorlog_path_name if set and
	 * sends email to config.errorlog_email_address if set
	 * as well as writing to 'normal' log by log method.
	 *
	 * @param string $message
	 * @param mixed  $extras
	 */
	public static function error($message, $extras = null) {
		static $log;

		if (!$log) {
			$log = new SS_Log();

			// if config.log_file_name set then log to this file in assets/logs/
			if ($logFilePathName = static::config()->get('errorlog_path_name')) {
				$log->add_writer(
					new SS_LogFileWriter(ASSETS_PATH . "/$logFilePathName")
				);
			}
			if ($emailErrorAddress = static::config()->get('errorlog_email_address')) {
				$log->add_writer(
					new SS_LogEmailWriter($emailErrorAddress)
				);
			}
		}
		static::log($message, SS_Log::ERR, $extras);
		$log->log($message, SS_Log::ERR, $extras);
	}

	/**
	 * Writes to config.log_path_name if set
	 *
	 * @param string $message
	 * @param mixed  $level
	 * @param mixed  $extras
	 */
	public static function log($message, $level = SS_Log::INFO, $extras = null) {
		static $log;

		if (!$log) {
			$log = new SS_Log();
			// if config.log_file_name set then log to this file in assets/logs/
			if ($logFilePathName = static::config()->get('log_path_name')) {
				$log->add_writer(
					new SS_LogFileWriter(
						ASSETS_PATH . "/$logFilePathName"
					)
				);
			}
		}
		$log->log($message, $level, $extras);
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