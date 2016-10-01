<?php
namespace Quaff\Transport;

use Quaff\Exceptions\Transport as Exception;
use Quaff\Interfaces\Endpoint;
use Quaff\Transport\HTTP\HTTP;

class CURL extends Transport {

	// valid options to pass to curl and their defaults
	private static $native_options = [
		self::ActionRead => [
			CURLOPT_HTTPGET        => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_NOBODY         => false,
		],
		self::ActionExists => [
			CURLOPT_HTTPGET        => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_NOBODY         => true,
		]
	];

	public function get($uri, array $options = []) {
		if (!function_exists('curl_init')) {
			throw new \BadFunctionCallException("curl is not available or installed");
		}

		list($resultCode, $result, $contentType) = static::native_request($uri, self::ActionRead, $options);

		return static::make_response(
			$this->getEndpoint(),
			$resultCode,
			$result,
			$contentType
		);
	}

	public function exists($uri, array $options = []) {
		list($resultCode, $result, $contentType) = static::native_request(
			$uri,
			self::ActionExists,
			$options)
		;

		return static::make_response(
			$this->getEndpoint(),
			$resultCode,
			$result,
			$contentType
		);
	}

	protected static function native_request($uri, $forActiom, array $options = []) {
		$curlHandle = curl_init($uri);

		curl_setopt_array(
			$curlHandle,
			static::native_options($forActiom, $options)
		);
		if (false !== ($result = curl_exec($curlHandle))) {
			$resultCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
			$result = curl_error($curlHandle);
			$contentType = curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE);
		} else {
			$resultCode = curl_errno($curlHandle);
			$result = curl_error($curlHandle);
			$contentType = 'text/plain';
		}
		return [
			$resultCode,
			$contentType,
		    $result
		];
	}

}