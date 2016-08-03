<?php

namespace Quaff\Transport;

use Modular\debugging;
use Quaff\Endpoint;
use GuzzleHttp\Client;
use Quaff\Exceptions\Exception;
use Quaff\Exceptions\Transport;
use Quaff\Responses\Response;
use Modular\Helpers\Debugger;

class Guzzle extends Transport {
	use debugging;

	const ContentTypeJSON     = 'application/json';
	const ContentTypeXML      = 'application/xml';
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

	protected $options = [];

	public function __construct(Endpoint $endpoint, array $options = []) {
		parent::__construct();
		$this->endpoint = $endpoint;
		$this->options($options);

		$options = array_merge_recursive(
			$this->headers(),
			$this->auth(),
			$this->options($options)
		);
		$this->client = new Client(
			$options
		);
	}

	public function options($options = null) {
		return $options ? $this->options = $options : $this->options;
	}

	/**
	 * @param array $uri
	 * @return array|\SimpleXMLElement
	 * @throws Exception
	 */
	public function get($uri) {
		try {
			/** @var Response $response */
			$response = $this->client->get(
				$uri
			);

			self::log_message('sync', Debugger::DebugInfo);
			self::log_message($response->getBody(), Debugger::DebugTrace);

			return $this->formatResponse($response);

		} catch (Transport $e) {
			// rethrow it
			throw $e;

		} catch (Exception $e) {
			throw new Transport($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Check response code is one of our 'OK' response codes from config.response_code_decode
	 *
	 * @param Response $response
	 * @return bool
	 */
	protected function isError(Response $response) {
		return static::match_response_code($response->getStatusCode(), self::ResponseDecodeError);
	}

	protected function isOK(Response $response) {
		return static::match_response_code($response->getStatusCode(), self::ResponseDecodeOK);
	}

	/**
	 * Decode the Guzzle Response into a QuaffApiResponse, which may be a QuaffApiErrorResponse if we got an
	 * error back from the api call.
	 *
	 * @param Response $response
	 * @return array|\SimpleXMLElement
	 * @throws Exception
	 */
	protected function formatResponse(Response $response) {
		if ($this->isError($response)) {
			return new QuaffApiErrorResponse($this->endpoint, [
				'Code'    => $response->getStatusCode(),
				'Message' => $response->getReasonPhrase(),
			    'Response' => $response
			]);
		}

		$responseContentType = $response->getHeader('Content-Type');
		$acceptType = $this->endpoint->getAcceptType();

		if (!static::match_content_type($responseContentType, $acceptType)) {
			throw new Transport("Bad response content type '$responseContentType', requested '$acceptType'");
		}
		switch ($this->endpoint->getAcceptType()) {
		case self::ContentTypeJSON:
			return $this->json($response->getBody());
		case self::ContentTypeXML:
			return $this->xml($response->getBody());
		default:
			throw new Transport("Can only handle json or xml at the moment");
		}
	}

	/**
	 * Check if a returned response code (e.g. 200) matches the expectation (e.g. DecodeResponseOK)
	 *
	 * @param $fromResponse
	 * @param $toExpected
	 * @return bool true response code matches expected, false otherwise
	 *
	 *              TODO: test all cases
	 */
	protected static function match_response_code($fromResponse, $toExpected) {
		$decode = static::config()->get('response_code_decode') ?: [];
		$res = (bool) count(
			$filtered = array_filter(
				$mapped = array_map(
					function ($pattern, $outcome) use ($fromResponse, $toExpected) {
						if (fnmatch($pattern, $fromResponse)) {
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
	 * Content types may have character encoding so just do a rude find of the expected content type in the response
	 * content type.
	 *
	 * @param array|string $contentType
	 * @param $expected
	 * @return bool true if matches, false otherwise
	 */
	protected static function match_content_type($contentType, $expected) {
		$contentTypes = is_array($contentType) ? $contentType : [ $contentType ];
		foreach ($contentTypes as $contentType) {
			if (false !== strpos(strtolower($contentType), strtolower($expected))) {
				return true;
			}
		}
		return false;
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