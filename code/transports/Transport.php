<?php
namespace Quaff\Transports;

use Injector;
use Modular\Application;
use Modular\debugging;
use Modular\Object;
use Modular\options;
use Modular\tokens;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Interfaces\Buffer;
use Quaff\Interfaces\Endpoint;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Transport as TransportInterface;

abstract class Transport extends Object implements TransportInterface {
	use debugging;
	use options;
	use tokens;

	protected $buffer;

	protected $endpoint;

	// these are used via injector in factory method  to get alternate registered transport services.
	private static $default_transport = 'DefaultTransport';
	private static $fallback_transport = 'FallbackTransport';

	// decode a 'native' response code or message to an ultimate 'OK' or 'Error' value
	// wildcards as used by fnmatch are accepted. The config.response_decode_ok and config.response_decode_error
	// are merged in here during comparison in response_code_decode
	private static $response_code_decodes = [
		# simple example which decodes a response of 'Yes', '200', '210' etc to 'OK', anything else to 'Error'
		#   'Yes' => self::ResponseDecodeOK,
		#   '2*' => self::response_decode_ok(),
		#   '*' => self::ResponseDecodeError
	];

	// these are implicitly added during response_code_decode, but may need to be changed incase they clash
	// with any native response codes, e.g. if 'OK' means 'Error' in some other language
	private static $response_decode_ok = self::ResponseDecodeOK;

	private static $response_decode_error = self::ResponseDecodeError;

	/**
	 * @return resource stream pointer, file, handle, object or some such
	 */
	public function getBuffer() {
		return $this->buffer;
	}

	/**
	 * @param $buffer
	 * @return mixed
	 * @fluent
	 */
	public function setBuffer($buffer) {
		$this->buffer = $buffer;
		return $this;
	}

	/**
	 * Reset/discard this transports buffer.
	 *
	 * @return void
	 */
	abstract public function discard();

	/**
	 * Try to open a uri for the specified action, return a stream pointer. Doesn't mutate the internal _stream.
	 *
	 * @param string      $uri       local filename or url including query string etc
	 * @param string      $forAction e.g. Transport::ActionRead
	 * @param mixed       $responseCode
	 * @param string|null $contentType
	 * @param int|null    $contentLength
	 * @return bool|resource open stream resource or false if failed
	 * @throws \Quaff\Exceptions\Transport
	 */
	abstract public function open($uri, $forAction, &$responseCode = null, &$contentType = null, &$contentLength = null);

	/**
	 * Open the url if possible and setup buffers for access, reading etc.
	 *
	 * @param $uri
	 * @param $responseCode
	 * @return mixed
	 */
	abstract public function buffer($uri, &$responseCode = null, &$contentType = null, &$contentLength = null);

	/**
	 * Return all or specific key from meta data.
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	abstract public function meta($key = null);

	/**
	 * Sadly we can't make this private/protected but try and use factory instead.
	 *
	 * @param \Quaff\Interfaces\Endpoint $endpoint
	 * @param array                      $options
	 */
	public function __construct(Endpoint $endpoint, $options = []) {
		parent::__construct();
		$this->endpoint = $endpoint;
		$this->options(static::config()->get('native_options'));
	}

	/**
	 * Returns the value which means 'OK' in a generic way (e.g. for a file we don't get an HTTP response code 200)
	 *
	 * @return mixed
	 */
	public static function response_decode_ok() {
		return static::config()->get('response_decode_ok');
	}

	/**
	 * Returns a human-readable version of an OK response message.
	 *
	 * @return mixed
	 */
	public static function response_message_ok() {
		return _t('Transport.Messages.OK', 'OK');
	}

	/**
	 * Returns the value which means 'Error' in a generic way (e.g. for a file we don't get an HTTP response code for why failed)
	 *
	 * @return mixed
	 */
	public static function response_decode_error() {
		return static::config()->get('response_decode_error');
	}

	/**
	 * Returns a human-readable version of an error response message.
	 * @return mixed
	 */
	public static function response_message_error() {
		return _t('Transport.Messages.Error', 'Error');
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
			$requested = $endpoint->getTransportClass(),
			$default = static::config()->get('default_transport'),
			$fallback = static::config()->get('fallback_transport'),
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
			throw new Exception("Unable to create a transport '$requested' after also trying, default '$default' and fallback '$fallback'");
		}
		return $transport;
	}

	/**
	 * @param string $uri
	 * @param array  $queryParams to pass to underlying transport mechanism, e.g. guzzle or curl or php context
	 * @return \Quaff\Interfaces\Response
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function get($uri, array $queryParams = []) {
		$uri = $this->safePathName($uri);

		$responseCode = null;

		$this->discard();
		if ($this->buffer($uri, $responseCode)) {
			$meta = $this->meta();
		} else {
			$meta = [];
		}

		return static::make_response(
			$this->getEndpoint(),
			$responseCode,
			$this,
			$meta
		);
	}

	/**
	 * @return \Quaff\Interfaces\Endpoint
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}
	
	public static function is_ok($responseCode) {
		return static::match_response_code($responseCode, self::ResponseDecodeOK);
	}
	
	public static function is_error($responseCode) {
		return !static::is_ok($responseCode);
			
	}

	/**
	 * Encapsulate the raw data from the transport into a Quaff Response, which may be a ErrorResponse if we got an
	 * error back from the api call. The data from transport may be e.g. a chunk of text encoded as json. html etc or a
	 * more complex object The response object created should be able to do something with the data representation passed in.
	 *
	 * @param Endpoint $endpoint
	 * @param          $resultCode
	 * @param Buffer   $buffer
	 * @param          $metaData
	 * @return \Quaff\Interfaces\Response
	 */
	public static function make_response(Endpoint $endpoint, $resultCode, Buffer $buffer, array $metaData = []) {
		if (static::match_response_code($resultCode, self::response_decode_ok())) {
			$responseClass = $endpoint->getResponseClass();
			$resultMessage = static::response_message_ok();
		} else {
			$responseClass = $endpoint->getErrorClass();
			$resultMessage = static::response_message_error();
		}
		// got an ok response code, e.g. 200
		return \Injector::inst()->create(
			$responseClass,
			$endpoint,
			$resultCode,
			$buffer,
			array_merge(
				[
					Transport::MetaResultMessage => $resultMessage
				],
				$metaData
			)
		);
	}
	
	/**
	 * Check if a returned response code (e.g. 200) matches the expectation (e.g. DecodeResponseOK)
	 *
	 * @param mixed      $fromCode
	 * @param int|string $toExpected a success or failure value (s.g. self::response_decode_ok())
	 * @return bool true response code matches expected, false otherwise
	 *
	 */
	public static function match_response_code($fromCode, $toExpected) {
		$decode = static::response_code_decodes();
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
	 * Given a local path or a uri return a local path within the web site root folder or the uri.
	 *
	 * @param string $uri may be changed!
	 * @param array  $options
	 * @return string
	 * @throws \Quaff\Exceptions\Transport
	 */
	protected function safePathName($uri, array $options = []) {
		if (static::config()->get('restrict_to_web_root')) {
			if (static::is_remote($uri)) {
				throw new Exception("Remote file requested when only web root files allowed");
			} else {
				$uri = Application::make_safe_path($uri, true);
			}
		}
		return $uri;
	}

	/**
	 * Check if a path starts with a configured remote schema, as a side-effect returns the first matched schema
	 *
	 * @param $path
	 * @return string|bool first matched schema or false if not matched
	 */
	public static function is_remote($path) {
		return current(
			array_filter(
				static::config()->get('remote_schemas'),
				function ($schema) use ($path) {
					return strtolower(substr($path, 0, strlen($schema))) == strtolower($schema);
				}
			)
		);
	}

	/**
	 * Return config.context_options merged with instance and passed options which have
	 * keys the same as config.context_options array top level, e.g. 'http', 'file'.
	 *
	 * @param string|null $action for options, e.g. self.ActionRead or if null all config.native_options
	 * @param array       $moreOptions
	 * @return mixed
	 */
	protected static function native_options($action, $moreOptions = []) {
		$optionsForAction = static::get_config_setting('native_options', $action) ?: [];
		return array_merge_recursive(
			$optionsForAction,
			array_intersect_key(
				$optionsForAction,
				$moreOptions
			)
		);
	}

	/**
	 * Return a map of codes to error/success conditions (wildcards allowed). This will always have the
	 * configured OK and Error responses first mappting to suitable Transport values which can be tested.
	 *
	 * @return array
	 */
	protected static function response_code_decodes() {
		return array_merge(
			[
				static::response_decode_ok() => Transport::ResponseDecodeOK,
			    static::response_decode_error() => Transport::ResponseDecodeError
			],
			static::config()->get('response_code_decodes') ?: []
		);
	}

}