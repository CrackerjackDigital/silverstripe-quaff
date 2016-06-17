<?php
use \Modular\ModularObject as Object;

/**
 * Provides an api where api calls can be configured and made.
 */
abstract class QuaffApi extends Object
	implements QuaffApiInterface, QuaffLocatorInterface
{
	/** @var string use this accept type with requests if none defined in the info */
	private static $accept_type = 'application/json';

	/** @var string the name of a QuaffAuth derived class*/
	private static $auth_provider = 'QuaffAuthTypeBasic';

	/** @var string the name of a QuaffTransport derived class */
	private static $transport = 'QuaffTransportGuzzle';


	private static $endpoints = [
#   E.G.
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
#           'class' => 'some model class'
#       ],
#       'post/some-private-endpoint' => [
#           'url' => 'some/private-endpoint',
#           'base' => 'post/private'
#           'class' => 'some model class'
#       ]
	];

	public function quaff(QuaffEndpointInterface $endpoint, array $params = [], $model = null) {
		return $endpoint->quaff($params, $model);
	}

	/**
	 *
	 * Find and return an api implementation for a service.
	 *
	 * @param string $path
	 *
	 * @return QuaffApiInterface
	 * @throws QuaffException
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
				'accept_type'    => static::config()->get('accept_type'),
				'url'       => null,
				'base'      => null,
				'transport' => static::config()->get('transport'),
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
}