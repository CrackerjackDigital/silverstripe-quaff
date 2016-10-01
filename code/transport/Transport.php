<?php
namespace Quaff\Transport;

use Injector;
use Modular\debugging;
use Modular\Object;
use Modular\options;
use Quaff\Interfaces\Endpoint;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Reader;
use Quaff\Interfaces\Transport as TransportInterface;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Transport\MetaData\http;

abstract class Transport extends Object implements TransportInterface {
	use debugging;
	use options;
	use http;

	const DefaultTransportService  = 'DefaultTransport';
	const FallbackTransportService = 'FallbackTransport';

	// default 'ok' response code, this may conflict with native response codes so may need
	// to be redefined in concrete classes
	const ResponseCodeOK = 0;

	protected $endpoint;

	protected $options;

	// decode a 'native' response code or message to an ultimate 'OK' or 'Error' value
	// wildcards as used by fnmatch are accepted
	private static $response_code_decode = [
		# simple example which decodes a response of 'Yes', '200', '210' etc to 'OK', anything else to 'Error'
		#   'Yes' => self::ResponseDecodeOK,
		#   '2*' => self::ResponseDecodeOK,
		#   '*' => self::ResponseDecodeError
	];

	/**
	 * Sadly we can't make this private/protected but try and use factory instead.
	 *
	 * @param \Quaff\Interfaces\Endpoint $endpoint
	 * @param array                      $options
	 */
	public function __construct(Endpoint $endpoint, $options = []) {
		parent::__construct();
		$this->endpoint = $endpoint;
		$this->options = $options;
	}

	/**
	 * Try and create endpoint.info.transport, DefaultTransport injector service or FallbackTransport injector service.
	 *
	 * @param EndpointInterface $endpoint
	 * @param array             $options
	 * @return \Quaff\Interfaces\Transport
	 * @throws \Exception
	 */
	public static function factory(EndpointInterface $endpoint, array $options = []) {
		$transports = array_filter([
			$endpoint->getTransportClass(),
			static::DefaultTransportService,
			static::FallbackTransportService,
		]);

		$transport = null;

		foreach ($transports as $className) {
			try {
				if ($transport = Injector::inst()->create($className, $endpoint, $options)) {
					break;
				}
			} catch (\Exception $e) {
				// try the next one
			}
		}
		if (!$transport) {
			throw new Exception("Unable to create a transport after trying defined, default and fallback");
		}
		return $transport;
	}

	public function getEndpoint() {
		return $this->endpoint;
	}

	public function getOptions() {
		return $this->options;
	}

	/**
	 * Decode the Guzzle Response into a Quaff Response, which may be a ErrorResponse if we got an
	 * error back from the api call.
	 *
	 * @param Endpoint $endpoint
	 * @param          $resultCode
	 * @param Reader   $reader
	 * @param          $metaData
	 * @return \Quaff\Responses\Response
	 */
	public static function make_response(Endpoint $endpoint, $resultCode, $result, array $metaData = []) {
		if (static::match_response_code($resultCode, self::ResponseDecodeOK)) {
			$responseClass = $endpoint->getResponseClass();
			$resultMessage = static::ResultMessageOK;
		} else {
			$responseClass = $endpoint->getErrorClass();
			$resultMessage = $result;
		}
		// got an ok response code, e.g. 200
		return \Injector::inst()->create(
			$responseClass,
			$endpoint,
			$resultCode,
			$result,
			array_merge(
				[
					self::MetaResultMessage => $resultMessage,
				],
				$metaData
			)
		);
	}

	/**
	 * Given a local path or a uri return a local path within the web site root folder or the uri.
	 *
	 * NB: May transform the passed $uri e.g. to sanitise or to show the 'real' path after rules are applied.
	 *
	 * @param string $uri may be changed!
	 * @param array  $options
	 * @return string
	 * @throws \Quaff\Exceptions\Transport
	 */
	protected function safePathName(&$uri, array $options = []) {
		$webRootOnly = static::config()->get('restrict_to_web_root');

		// test if remote by checking there is an 'http://' or 'https://' component in the uri
		if (static::is_remote($uri)) {
			if ($webRootOnly) {
				$uri = $this->sanitiseURI($uri);
				throw new Exception("Remote file '$uri' requested when only web root files allowed");
			}
		} else {
			$baseFolder = \Director::baseFolder();

			if (substr($uri, 0, 1) == '/') {
				$uri = $baseFolder . $uri;
			} else {
				$uri = ASSETS_PATH . '/' . $uri;
			}
			// strip off query string if present
			$uri = current(explode('?', $uri, 2));

			if ($webRootOnly && substr(realpath($uri), 0, strlen($baseFolder)) != $baseFolder) {
				throw new Exception("Invalid path '$uri' not in the web root");
			}
		}
		return $uri;
	}

	public static function response_code_ok() {
		return static::ResponseCodeOK;
	}

	public static function is_local($path) {
		return !static::is_remote($path);
	}

	public static function is_remote($path) {
		return stream_is_local($path);
	}

	/**
	 * Return config.context_options merged with instance and passed options which have
	 * keys the same as config.context_options array top level, e.g. 'http', 'file'.
	 *
	 * @param mixed $type
	 * @param array $moreOptions
	 * @return mixed
	 */
	protected static function native_options($type, array $moreOptions = []) {
		$optionsForAction = static::get_config_setting('native_options', $type);
		return array_merge_recursive(
			$optionsForAction,
			array_intersect_key(
				$optionsForAction,
				$moreOptions
			)
		);
	}

	/**
	 * Check if a returned response code (e.g. 200) matches the expectation (e.g. DecodeResponseOK)
	 *
	 * @param mixed      $fromCode
	 * @param int|string $toExpected a success or failure value (s.g. self::ResponseDecodeOK)
	 * @return bool true response code matches expected, false otherwise
	 *
	 */
	protected static function match_response_code($fromCode, $toExpected = self::ResponseDecodeOK) {
		$decode = static::response_code_decode();
		foreach ($decode as $pattern => $result) {
			if (fnmatch($pattern, $fromCode)) {
				if ($result === $toExpected) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Return a map of codes to error/success conditions (wildcards allowed).
	 *
	 * @return array
	 */
	public static function response_code_decode() {
		return static::config()->get('response_code_decode');
	}

}