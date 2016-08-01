<?php
use GuzzleHttp\Client as Client;
use GuzzleHttp\Psr7\Response as Response;

class QuaffTransportGuzzle extends QuaffTransport {
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

	/** @var QuaffEndpoint */
	protected $endpoint;

	/** @var Client */
	protected $client;

	protected $options = [];

	public function __construct(QuaffEndpoint $endpoint, array $options = []) {
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
	 * @return array|SimpleXMLElement
	 * @throws QuaffException
	 */
	public function get($uri) {
		try {
			/** @var Response $response */
			$response = $this->client->get(
				$uri
			);

			self::debugging(
				ModularDebugger::DebugFile | ModularDebugger::DebugTrace,
				'sync'
			)->trace($response->getBody(), __FUNCTION__);

			return $this->formatResponse($response);

		} catch (QuaffTransportException $e) {
			// rethrow it
			throw $e;

		} catch (Exception $e) {
			throw new QuaffTransportException($e->getMessage(), $e->getCode(), $e);
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
	 * @return array|SimpleXMLElement
	 * @throws QuaffException
	 */
	protected function formatResponse(Response $response) {
		if ($this->isError($response)) {

			return new QuaffApiErrorResponse($this->endpoint, [
				'Code'    => $response->getStatusCode(),
				'Message' => $response->getMessage(),
				'URI'     => $response->getEffectiveUrl(),
			]);
		}
		if (!static::match_content_type($response->getContentType(), $this->endpoint->getAcceptType())) {
			throw new QuaffTransportException("Bad response content type '" . $response->getContentType() . "'");
		}
		switch ($this->endpoint->getAcceptType()) {
		case self::ContentTypeJSON:
			$method = 'json';
			break;
		case self::ContentTypeXML:
			$method = 'xml';
			break;
		default:
			throw new QuaffTransportException("Can only handle json or xml at the moment");
		}
		return $response->$method();
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
	 * @param $fromResponse
	 * @param $toExpected
	 * @return bool true if matches, false otherwise
	 */
	protected static function match_content_type($fromResponse, $toExpected) {
		return false !== strpos(strtolower($fromResponse), strtolower($toExpected));
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