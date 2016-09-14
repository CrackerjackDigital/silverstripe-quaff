<?php

namespace Quaff\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Modular\debugging;
use Modular\Debugger;
use Modular\options;
use Quaff\Endpoints\Endpoint;
use Quaff\Exceptions\Transport as Exception;

class Guzzle extends Transport {
	use debugging;
	use options;

	const ResponseDecodeOK    = 'ok';
	const ResponseDecodeError = 'error';

	/** @var array allow multiple response codes to be treated as 'OK', wildcards acceptable as per fnmatch */
	private static $response_code_decode = [
		'2*' => self::ResponseDecodeOK,
		'3*' => self::ResponseDecodeError,
		'4*' => self::ResponseDecodeError,
		'5*' => self::ResponseDecodeError,
	];

	/** @var Endpoint */
	protected $endpoint;

	/** @var Client */
	protected $client;

	public function __construct(Endpoint $endpoint, array $options = []) {
		parent::__construct();
		$this->endpoint = $endpoint;
		$this->options($options);

		$options = array_merge_recursive(
			$this->headers(),
			$this->auth(),
			$this->options()
		);
		$this->client = new Client(
			$options
		);
	}

	/**
	 * @param array $uri
	 * @param array $params
	 * @return array|\SimpleXMLElement
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function get($uri, array $params = []) {
		try {
			/** @var GuzzleResponse $response */
			$response = $this->client->get(
				$uri
			);

			self::debug_message('sync', Debugger::DebugInfo);
			self::debug_message($response->getBody(), Debugger::DebugTrace);

			return static::make_response($this->endpoint, $response);

		} catch (Exception $e) {
			throw new Exception($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Check response code is one of our 'OK' response codes from config.response_code_decode
	 *
	 * @param $code
	 * @return bool
	 */
	protected function isError($code) {
		return static::match_response_code($code, self::ResponseDecodeError);
	}

	protected function isOK($code) {
		return static::match_response_code($code, self::ResponseDecodeOK);
	}

	/**
	 * Decode the Guzzle Response into a Quaff Response, which may be a ErrorResponse if we got an
	 * error back from the api call.
	 *
	 * @param \Quaff\Endpoints\Endpoint $endpoint
	 * @param GuzzleResponse            $response
	 * @return \Quaff\Responses\Response
	 */
	public static function make_response(Endpoint $endpoint, GuzzleResponse $response) {
		if (static::match_response_code($response->getStatusCode(), self::ResponseDecodeOK)) {

			return \Injector::inst()->create(
				$endpoint->getResponseClass(),
				$endpoint,
				$response->getBody(),
				[
					'ResultCode'  => $response->getStatusCode(),
					'ContentType' => $response->getHeader('Content-Type'),
				]
			);
		}
		return \Injector::inst()->create(
			$endpoint->getErrorClass(),
			$endpoint,
			$response->getBody(),
			[
				'ResultCode'    => $response->getStatusCode(),
				'ResultMessage' => $response->getReasonPhrase(),
				'ContentType'   => $response->getHeader('Content-Type'),
			]
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
		$decode = static::response_decode();

		$res = (bool) count(
			$filtered = array_filter(
				$mapped = array_map(
					function ($pattern, $outcome) use ($fromCode, $toExpected) {
						if (fnmatch($pattern, $fromCode)) {
							return $outcome === $toExpected;
						}
						return false;
					},
					array_keys($decode),
					array_values($decode)
				)
			)
		);
		return $res;
	}

	/**
	 * Return a map of codes to error/success conditions (wildcards allowed).
	 *
	 * @return array
	 */
	public static function response_decode() {
		return [
			'1*' => self::ResponseDecodeOK,
			'2*' => self::ResponseDecodeOK,
			'3*' => self::ResponseDecodeOK,
			'4*' => self::ResponseDecodeError,
			'5*' => self::ResponseDecodeError,
		];
	}

	/**
	 * Merge in headers
	 *
	 * @return array
	 * @internal param $info
	 */
	protected function headers() {
		return [
			'request.options' => [
				'headers' => [
					'Accept' => $this->endpoint->getAcceptType(),
				],
			],
		];
	}

	/**
	 * @return array
	 */
	protected function auth() {
		return $this->endpoint->auth() ?: [];
	}
}