<?php
namespace Quaff\Transport;

use Modular\config;
use Quaff\Exceptions\Transport as Exception;

/**
 * Reads data from a local file, one item per line in the file.
 *
 * @package Quaff\Transport
 */
class LocalJSONFile extends Transport {
	use config;

	const NoMoreItemsResultCode = 'NoMoreItems';

	// if we are on page 1, then we offset into the file by page - this setting to get the 0th item
	const PageStartOffset = 1;

	// don't limit
	const DefaultMaxItemsPerCall = 0;
	const DefaultRestrictToWebRoot = true;

	const ResponseCodeOK = 200;

	private static $max_items_per_call = self::DefaultMaxItemsPerCall;

	private static $restrict_to_web_root = self::DefaultRestrictToWebRoot;

	protected $endpoint;
	protected $options;

	public function __construct(\Quaff\Interfaces\Endpoint $endpoint, $options = []) {
		parent::__construct();
		$this->endpoint = $endpoint;
		$this->options = $options;
	}
	public function get($uri, array $params = []) {
		$endpoint = $this->endpoint;
		$baseFolder = \Director::baseFolder();

		if (substr($uri, 0, 1) == '/') {
			$uri = $baseFolder . $uri;
		} else {
			$uri = ASSETS_PATH . '/' . $uri;
		}
		// strip off query string if present
		$filePathName = current(explode('?', $uri, 2));

		if ($this->config()->get('restrict_to_web_root') && substr(realpath($filePathName), 0, strlen($baseFolder)) != $baseFolder) {
			throw new Exception("Path '$filePathName' is invalid as it is not in the web root");
		}

		if (!is_file($filePathName)) {
			throw new Exception("No such file: '$filePathName'");
		}

		if (!$data = file_get_contents($filePathName)) {
			throw new Exception("Empty or bad file '$filePathName', no data");
		}

		$items = json_decode($data, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception("json_decode error: " . json_last_error_msg());
		}

		if ($pagination = $endpoint->info('pagination')) {
			$pageVar = $pagination['page_var'];
			$lengthVar = $pagination['length_var'];

			$pageNum = array_key_exists($pageVar, $params) ? $params[$pageVar] : null;
			$pageLength = array_key_exists($lengthVar, $params) ? $params[ $lengthVar ] : null;

			if (!is_null($pageNum) && !is_null($pageLength)) {
				$start = ($pageNum - static::PageStartOffset) * $pageLength;
				$items = array_values(array_slice($items, $start, $pageLength, true));
			}
		}
		// anything other than OK should stop iterating on responses
		$resultCode = count($items)
			? static::ResponseCodeOK
			: static::NoMoreItemsResultCode;

		$responseClass = $endpoint->getResponseClass();
		return new $responseClass(
			$endpoint,
			json_encode($items),
			[
				'ResultCode'  => $resultCode,
				'ContentType' => 'application/json'
			]
		);
	}
	public static function response_code_ok() {
		return static::ResponseCodeOK;
	}
}
