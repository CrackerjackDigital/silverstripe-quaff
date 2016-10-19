<?php
namespace Quaff\Transport;

use Quaff\Endpoints\Endpoint;
use Quaff\Interfaces\Response;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Transport\Buffers\passthru;
use Quaff\Transport\MetaData\http;

/**
 * transport that works for local files and also remote files using php file/stream wrappers.
 *
 * @package Quaff\Transport
 */
abstract class Stream extends Transport {
	use http;
	use passthru;

	private static $response_code_decode = [
		self::ResponseCodeOK => self::ResponseDecodeOK,
		'2*'                 => self::ResponseDecodeOK,
		'*'                  => self::ResponseDecodeError,
	];

	/** @var array php context options eg for stream_context_create */
	private static $native_options = [
		self::ActionExists => [
			'http' => [
				'method' => 'HEAD',
			],
			'file' => [],
		],
		self::ActionRead   => [
			'http' => [
				'method' => 'GET',
			],
			'file' => [],
		],
	];

	public function __construct(Endpoint $endpoint, array $options = []) {
		parent::__construct($endpoint, $options);

		$this->options($this->config()->get('native_options'));
	}

	/**
	 * @param string $uri
	 * @param array  $options to pass to underlying transport mechanism, e.g. guzzle or curl or php context
	 * @return \Quaff\Interfaces\Response
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function get($uri, array $options = []) {
		if (!$this->ping($uri, $responseCode, $contentType, $contentLength)) {
			throw new Exception("No such file: '" . $this->sanitisePath($uri) . "'");
		}
		// buffer in the context of a stream is a file pointer which can be read from
		$buffer = $this->buffer($uri, $responseCode, $contentType, $contentLength);

		return static::make_response(
			$this->getEndpoint(),
			$buffer ? self::ResponseCodeOK : $responseCode,
			$buffer,
			[
				self::MetaContentType   => $contentType,
				self::MetaContentLength => $contentLength,
			]
		);
	}

	public function ping($uri, &$responseCode, &$contentType = null, &$contentLength = null) {
		if ($this->is_local($uri)) {
			$filePathName = $this->safePathName($uri);
			if (!file_exists($filePathName) && is_file($filePathName)) {
				return false;
			}
			$responseCode = self::ResponseCodeOK;
			$contentLength = filesize($filePathName);
			$contentType = mime_content_type($filePathName) ?: null;
		} else {
			// try and open remote file using a HEAD request and read meta data if returned.
			if (!$fp = fopen($uri, 'r', null, $this->native_options(self::ActionExists))) {
				return false;
			}
			if ($meta = stream_get_meta_data($fp)) {
				if ($meta['wrapper_type'] == 'http' && isset($meta['wrapper_data'])) {
					$meta = $meta['wrapper_data'];

					$responseCode = current($this->decodeMetaData($meta, 0, ' ', 1)) ?: null;
					$contentType = current($this->decodeMetaData($meta, 'Content-Type')) ?: null;
					$contentLength = current($this->decodeMetaData($meta, 'Content-Length')) ?: null;
				}
			}
			fclose($fp);
		}
		return true;
	}
}