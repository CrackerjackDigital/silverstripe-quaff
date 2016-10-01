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
	 * @param string       $what               e.g. 'Content-Type' or one of the class HTTP.HeaderABC constants.
	 * @param string       $multiPartSeperator separator between multipart values
	 * @param int          $multiPartPart      1 based index of the part to return from multipart values
	 * @return array of matched headers
	 */
	public function decodeMetaData($metaData, $what, $multiPartSeperator = ';', $multiPartPart = 1) {
		if (!is_array($metaData)) {
			$metaData = explode("\n", $metaData);
		}
		// always append one ':' and compare in lower case
		$what = trim(strtolower($what), ':') . ':';
		$whatLength = strlen($what);

		return array_filter(
			array_map(
				function ($line) use ($what, $whatLength, $multiPartSeperator, $multiPartPart) {
					if (substr(strtolower($line), 0, $whatLength) == $what) {
						if (false !== strpos($line, $multiPartSeperator)) {
							$parts = explode($line, $multiPartSeperator);
							return trim($parts[ $multiPartPart - 1 ]);
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
