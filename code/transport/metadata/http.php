<?php
namespace Quaff\Transport\MetaData;
/**
 * Decodes http headers
 *
 * @package Quaff\Transport\MetaData
 */
trait http {
	/**
	 * Return uri rebuilt without the password if it was there.
	 *
	 * @param string $uri
	 * @return string
	 */
	public function sanitiseURI($uri) {
		if (stream_is_local($uri)) {
			return $uri;
		} else {
			return http_build_url(parse_url($uri, ['scheme', 'user', 'host', 'port', 'query', 'fragment']));
		}
	}

	/**
	 * @param string|array $metaData           string of all headers or array of headers
	 * @param string       $what               e.g. 0 for first header, 'Content-Type' or one of the class HTTP.HeaderABC constants.
	 * @param string       $multiPartSeperator separator between multipart values
	 * @param int          $multiPartIndex     0 based index of the part to return from multipart values
	 * @return array of matched headers (maybe empty if nothing matches)
	 */
	public function decodeMetaData($metaData, $what, $multiPartSeperator = ';', $multiPartIndex = 0) {
		if (!is_array($metaData)) {
			$metaData = explode("\n", $metaData);
		}
		if (is_numeric($what)) {
			return array_key_exists($what, $metaData) ? $metaData[$what] : [];
		} else {
			// always append one ':' and compare in lower case
			$what = trim(strtolower($what), ':') . ':';
		}
		$whatLength = strlen($what);

		return array_filter(
			array_map(
				function ($line) use ($what, $whatLength, $multiPartSeperator, $multiPartIndex) {
					if (substr(strtolower($line), 0, $whatLength) == $what) {
						if (false !== strpos($line, $multiPartSeperator)) {
							$parts = explode($line, $multiPartSeperator);
							return trim($parts[ $multiPartIndex ]);
						} else {
							return $line;
						}
					}
					return null;
				},
				$metaData
			)
		);
	}
}
