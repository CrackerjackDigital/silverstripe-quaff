<?php
namespace Quaff\Transports\Protocol;

use Quaff\Exceptions\Transport as Exception;

/**
 * Decodes http headers
 *
 * @package Quaff\Transports\Protocol
 */
trait http {
	/**
	 * @return \Config_ForClass
	 */
	abstract public function config($className = null);

	/**
	 * Return uri rebuilt without the password if it was there.
	 *
	 * @param string $uri
	 * @return string
	 */
	public function sanitisePath($uri) {
		if (static::is_remote($uri)) {
			return http_build_url(parse_url($uri, ['scheme', 'user', 'host', 'port', 'query', 'fragment']));
		} else {
			return $uri;
		}
	}

	/**
	 * Build a query string, we trust the parameters to have been properly encoded already.
	 *
	 * TODO handle arrays as values
	 *
	 * @param string $url
	 * @param string $action          e.g. Transport::ActionRead used to add default parameters from config
	 * @param array  $queryParams     map of name => value which will be appended as query string
	 * @param array  $tokens          map of token => value which will be replaced in the built uri
	 * @param array  $tokenDelimiters which mark start and end of a token, may need changing if default tokens are meaningful in url
	 * @return string
	 */
	public function prepareURI($url, $action, array $queryParams = [], array $tokens = [], array $tokenDelimiters = ['{', '}']) {
		$query = http_build_query($this->queryParams(
			$action,
			$queryParams
		));
		$url = rtrim($url, '?') . ($query ? "?$query" : '');

		return $this->detokenise(
			$url,
			$tokens,
			$tokenDelimiters
		);
	}

	/**
	 * Returns an array of query string segments in preference of config.params.get, model fields then params.
	 *
	 * @param string $action e.g. ActionRead, ActionExists so default parameters for correct action can be added
	 * @param array  $params by reference so can do e.g. pager extensions
	 * @return array
	 */
	protected function queryParams($action, array &$params = []) {
		$params = array_merge(
			static::get_config_setting('default_params', $action) ?: [],
			$params
		);
		$this->extend('updateQueryParameters', $params);
		return $params;
	}

	/**
	 * Replaces tokens in the url which values from params. Override in concrete classes to provide custom url mangling.
	 *
	 * @param array $params
	 * @param null  $model
	 * @return array map of token name => value for parameters to add to the remote uri called, e.g. [ 'id' => 1212 ]
	 */
	protected function uriParams(array &$params, $model = null) {
		$this->extend('updateURIParameters', $params, $model);
		return $params;
	}

	/**
	 * Given a Transport.MetaABC constant returns the key to use in decodeMetaData. If the key is not
	 * in the map then just return the key as it may be verbatim or a numeric key.
	 *
	 * @param $key
	 * @return mixed
	 */
	public function transportToMetaKey($key) {
		// should be a map of Transport.MetaABC constant => http header name, e.g. 'Content-Type'
		if ($map = ($this->config()->get('transport_to_meta_keys') ?: [])) {
			if (array_key_exists($key, $map)) {
				return $map[ $key ];
			}
		}
		return $key;
	}

	/**
	 * Decode meta data as from an http request/response (string or array) and return all of it or if key supplied the entry selected.
	 * If not found returns null.
	 *
	 * @param string|array $metaData           string or array of http headers
	 * @param string       $what               e.g. 0 for first header, 'Content-Type' or one of the class HTTP.HeaderABC constants.
	 * @param string       $multiPartSeperator separator between multipart values
	 * @param int          $multiPartIndex     0 based index of the part to return from multipart values
	 * @return array of matched headers (maybe empty if nothing matches) or null if no information
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function decodeMetaData($metaData, $what = null, $multiPartSeperator = ';', $multiPartIndex = 0) {

		if (!is_array($metaData)) {
			// normalise to array, RFC says always a "\n"
			$metaData = explode("\n", $metaData);
		}
		$translated = [];
		// convert http keys to quaff transport keys, e.g. Transport::MetaContentType
		foreach ($metaData as $line) {
			list($key, $value) = explode(':', $line, 2);

			if ($localKey = $this->transportToMetaKey($key)) {
				$translated[$localKey] = $value;
			} else {
				// we do process 'unknown' keys, though they are mangled a bit so no clash with internal 'known' keys
				// and to make lookup more bulletproof
				$translated[strtolower($key)] = $value;
			}
		}
		if (is_null($what)) {
			// early return we're done.
			return $translated;
		}
		if (is_numeric($what)) {
			if ($what < count($translated)) {
				return $this->extractMultiPart(array_values($translated)[$what], 0, $multiPartSeperator, $multiPartIndex);
			} else {
				throw new Exception("Bad numeric key: $what");
			}
		}
		if (!$key = $this->transportToMetaKey($what)) {
			throw new Exception("Can't get native key for Transport meta key '" . $what . "'");
		}
		// potentially we could encode the key for unknown headers somehow to stop collisions
		// for now we're just going to lowercase it for lookup
		$mangledWhat = strtolower($what);

		if (array_key_exists($key, $translated)) {

			// try and look it up by Transport::MetaABC key
			return $this->extractMultiPart($translated[$key], $key, $multiPartSeperator, $multiPartIndex);
		} elseif (array_key_exists($mangledWhat, $translated)) {

			// try mangled $what incase an 'unknown' key
			return $this->extractMultiPart($translated[ $mangledWhat ], $key, $multiPartSeperator, $multiPartIndex);
		} else {

			throw new Exception("Unknown meta data key '$what'");
		}
	}

	/**
	 * Given a line which can be parsed out into multiple parts, such as 'Content-Type: text/csv; charset=utf-8' parse the line out and returns
	 * the requested line part. If the multi part seperator is not found then just return the line after keyLength.
	 *
	 *  -   ('Content-Type: text/csv; charset=utf-8', 12, ';', 0) = 'text/csv'
	 *  -   ('Content-Type: text/csv; charset=utf-8', 12, ';', 1) = 'charset=utf-8'
	 *
	 * @param $line
	 * @param $keyLength
	 * @param $multiPartSeperator
	 * @param $multiPartIndex
	 * @return string|null
	 */
	public function extractMultiPart($line, $keyLength, $multiPartSeperator, $multiPartIndex) {
		if (false === strpos($line, $multiPartSeperator)) {
			// return part of line after the key
			return trim(substr($line, $keyLength)) ?: null;

		} else {
			// return requested part of the multi-part line
			$parts = explode($line, $multiPartSeperator);

			if ($multiPartIndex < count($parts)) {
				return trim($parts[ $multiPartIndex ]) ?: null;
			} else {
				return null;
			}
		}
	}

	/**
	 * Return the content type by decoding from the file extension.
	 *
	 * @param $filePathName
	 * @return null
	 */
	protected function contentTypeFromURI($filePathName) {
		$mimetypes = $this->config()->get('mimetype_for_extension') ?: [];
		$extension = strtolower(pathinfo($filePathName, PATHINFO_EXTENSION));

		return isset($mimetypes[ $extension ])
			? $mimetypes[ $extension ]
			: null;
	}
}
