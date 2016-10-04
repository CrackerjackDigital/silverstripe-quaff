<?php

namespace Quaff\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Modular\debugging;
use Modular\Debugger;
use Modular\options;
use Quaff\Endpoints\Endpoint;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Transport\HTTP\HTTP;

class Guzzle extends Transport {

	/** @var Client */
	protected $client;

	public function __construct(Endpoint $endpoint, array $options = []) {
		parent::__construct($endpoint, $options);

		$this->options = $this->options(array_merge_recursive(
			$this->headers(),
			$this->auth(),
			$this->options()
		));
		$this->client = new Client(
			$options
		);
	}

	/**
	 * @param array $uri
	 * @param array $options
	 * @return array|\SimpleXMLElement
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function get($uri, array $options = []) {
		try {
			/** @var GuzzleResponse $response */
			$response = $this->client->get(
				$uri
			);

			self::debug_message('sync', Debugger::DebugInfo);
			self::debug_message($response->getBody(), Debugger::DebugTrace);

			return static::make_response($this->getEndpoint(), $response);

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
		if (!static::match_response_code($response->getStatusCode(), self::ResponseDecodeOK)) {
			// fail
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
		// ok
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