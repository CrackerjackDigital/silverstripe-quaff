<?php
namespace Quaff\Responses;

use ArrayList;
use Modular\NotImplementedException;
use Modular\Object;
use Quaff\Exceptions\Response as Exception;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Response as ResponseInterface;
use Quaff\Interfaces\Mapper as MapperInterface;
use Quaff\Mappers\ArrayMapper;

// roll on php 5.6 wider support so can use expressions in constanta!
if (!defined('QUAFF_RESPONSE_DEFAULT_JSON_DECODE_OPTIONS')) {
	define('QUAFF_RESPONSE_DEFAULT_JSON_DECODE_OPTIONS', true);
}
if (!defined('QUAFF_RESPONSE_DEFAULT_XML_DECODE_OPTIONS')) {
	define('QUAFF_RESPONSE_DEFAULT_XML_DECODE_OPTIONS', LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_PARSEHUGE | LIBXML_PEDANTIC);
}
if (!defined('QUAFF_RESPONSE_DEFAULT_HTML_DECODE_OPTIONS')) {
	define('QUAFF_RESPONSE_DEFAULT_HTML_DECODE_OPTIONS', LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_PARSEHUGE | LIBXML_PEDANTIC | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS);
}

abstract class Response extends Object implements ResponseInterface {
	const SimpleMatchKey = 'request';

	const RawDataArray = 'array';

	const OKMessage   = 'OK';
	const FailMessage = 'Failed';

	const ContentTypeJSON = 'json';
	const ContentTypeXML  = 'xml';
	const ContentTypeHTML = 'html';

	const DefaultJSONOptions = QUAFF_RESPONSE_DEFAULT_JSON_DECODE_OPTIONS;
	const DefaultXMLOptions  = QUAFF_RESPONSE_DEFAULT_XML_DECODE_OPTIONS;
	const DefaultHTMLOptions = QUAFF_RESPONSE_DEFAULT_HTML_DECODE_OPTIONS;

	private static $content_types = [
		self::ContentTypeJSON => ['application/json'],
		self::ContentTypeXML  => ['application/xml', 'text/xml'],
		self::ContentTypeHTML => ['text/html', 'application/xhtml+xml'],
	];

	private static $decode_options = [
		self::ContentTypeJSON => self::DefaultJSONOptions,
		self::ContentTypeXML  => self::DefaultXMLOptions,
		self::ContentTypeHTML => self::DefaultHTMLOptions,
	];

	protected $rawData;

	protected $metaData;

	/** @var EndpointInterface */
	protected $endpoint = null;

	/**
	 * Response constructor.
	 *
	 * @param EndpointInterface $endpoint
	 * @param string            $rawData  e.g. body of HTTP response
	 * @param array             $metaData extra information such as headers
	 */
	public function __construct(EndpointInterface $endpoint, $rawData, $metaData = null) {
		$this->endpoint = $endpoint;
		$this->rawData = $rawData;
		$this->metaData = $metaData;
		parent::__construct();
	}

	public function __get($name) {
		return $this->hasMethod("get$name")
			? $this->{"get$name"}()
			: $this->data($name);
	}

	/**
	 * Return native or implementation defined status code, e.g. '200' for HTTP.
	 *
	 * @return mixed
	 */
	public function getResultCode() {
		return $this->meta('ResultCode');
	}

	/**
	 * Return a useful translated message, e.g. 'ok' or error text.
	 *
	 * @return string|null
	 */
	public function getResultMessage() {
		return $this->meta('ResultMessage') ?: self::FailMessage;
	}

	/**
	 * Return the type of raw content we got back in the response to the request
	 * e.g. 'application/json' or 'text/html' for an http response.
	 *
	 * @return string
	 */
	public function getContentType() {
		return $this->meta('ContentType');
	}

	/**
	 * Return the items returned by the request as a list.
	 *
	 * @param int $options
	 * @return \SS_List
	 */
	public function getItems($options = MapperInterface::DefaultOptions) {
		return $this->items($options);
	}

	/**
	 * Return list of Models populated from the raw data.
	 *
	 * Items are either existing found using 'findModel' or new models via 'makeModel'
	 * updated from the item data via their 'quaff' method.
	 *
	 * @param  array|int $options
	 * @return \ArrayList
	 * @throws \Modular\NotImplementedException
	 * @throws \Quaff\Exceptions\Response
	 */
	protected function items($options = null) {
		static $models;

		if ($models === false) {
			$models = new ArrayList();

			if ($this->isValid()) {
				$contentType = $this->getContentType();

				if ($type = static::decode_content_type($contentType)) {
					$items = $this->$type($options);
				} else {
					throw new Exception("Bad content type '$contentType'");
				}
				foreach ($items as $item) {
					/** QuaffModelInterface */
					if (!$model = $this->findModel($item, $options)) {
						$model = $this->endpoint->modelFactory($item, $options);
					}
					// call this directly instead of extend.
					$model->quaff($this->endpoint, $item, $options);

					$models->push($model);
				}

			}
		}
		return $models;
	}

	/**
	 * Content types may have character encoding so just do a rude find of the expected content type in the response
	 * content type starting from the first character in lower-case.
	 *
	 * @param string $contentType we are looking to decode
	 * @return int|null the ContentTypeACB constant for the string content type or null if not found
	 */
	protected static function decode_content_type($contentType) {
		$contentTypes = static::config()->get('content_types');

		foreach ($contentTypes as $type => $signatures) {
			foreach ($signatures as $signature) {
				if (0 === strpos(strtolower($contentType), strtolower($signature))) {
					return $type;
				}
			}
		}
		return null;
	}

	/**
	 * Return rawData as json
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function json() {
		if (is_null($json = json_decode($this->rawData, $this->get_config_setting('decode_options', self::ContentTypeJSON)))) {
			$message = "Failed to load json from response raw data";
			if ($error = json_last_error()) {
				$message .= ": '$error'";
			}
			throw new Exception($message);
		}
		if ($itemPath = $this->endpoint->getItemPath()) {
			$value = ArrayMapper::traverse($itemPath, $json, $found);

			if (!$found) {
				throw new Exception("Item node '$itemPath' not found in response");
			}
			return $value;
		} else {
			return $json;
		}
	}

	/**
	 * Return provided text as an xml DOM Document.
	 *
	 * @return \DOMDocument
	 * @throws Exception
	 */
	protected function xml() {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$doc = new \DOMDocument();
		if (!$doc->loadXML($this->rawData, $this->get_config_setting('decode_options', self::ContentTypeXML))) {

			$message = "Failed to load document from response raw data";
			if ($error = libxml_get_last_error()) {
				$message .= ": '$error'";
			}
			throw new Exception($message);
		}
		$xpath = new \DOMXPath($doc);
		if ($itemPath = $this->endpoint->getItemPath()) {
			return $xpath->query($itemPath);
		} else {
			return $xpath->query('/');
		}
	}

	/**
	 * Return provided text as an xml DOM Document.
	 *
	 * @return \DOMDocument
	 * @throws Exception
	 */
	protected function html() {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$doc = new \DOMDocument();
		if (!$doc->loadHTML($this->rawData, $this->get_config_setting('decode_options', self::ContentTypeHTML))) {
			$message = "Failed to load document from response raw data";

			if ($error = libxml_get_last_error()) {
				$message .= ": '$error'";
			}
			throw new Exception($message);
		}
		$xpath = new \DOMXPath($doc);
		if ($itemPath = $this->endpoint->getItemPath()) {
			return $xpath->query($itemPath);
		} else {
			return $xpath->query('/');
		}
	}

	/**
	 * Call through to Endpoint, allow overload here. Returns a new model optionally initialised with passed data.
	 *
	 * @param array $data
	 * @param int   $flags
	 * @return \Quaff\Interfaces\Mapper
	 */
	protected function newModel(array $data = null, $flags = null) {
		return $this->getEndpoint()->modelFactory($data);
	}

	/**
	 * Return an existing model from the provided item data or return null if not found. Override in implementation to
	 * find an existing model. By default returns null.
	 *
	 * @param array $data
	 * @param       $flags
	 * @return \DataObject|null
	 * @throws \Modular\NotImplementedException
	 */
	protected function findModel(array $data, $flags) {
		throw new NotImplementedException("Please provide implementation in concrete class");
	}

	/**
	 * @return EndpointInterface
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}

	/**
	 * Return immediate data in first 'tier' of returned data without traversing a path, quicker than traverse.
	 *
	 * @param $key
	 * @return array
	 */
	public function data($key = null) {
		return array_key_exists($key, $this->rawData) ? $this->rawData[ $key ] : null;
	}

	/**
	 * Return metaData value by key
	 *
	 * @param $key
	 * @return array
	 */
	public function meta($key = null) {
		return array_key_exists($key, $this->metaData) ? $this->metaData[ $key ] : null;
	}

	public function getURI() {
		return $this->meta('URI');
	}

	/**
	 * Call from inherited classes for basic validity checks before specific ones.
	 * Initially just returns the opposite of isError.
	 *
	 * @return bool
	 */
	public function isValid() {
		return !$this->isError();
	}

	/**
	 * Returns the raw data from the response.
	 *
	 * @return array
	 * @internal param string $format - does nothing at the moment, always returns an array.
	 */
	public function getRawData() {
		return $this->rawData;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 *                      An offset to check for.
	 *                      </p>
	 * @return boolean true on success or false on failure.
	 *                      </p>
	 *                      <p>
	 *                      The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset) {
		return is_array($this->rawData) && array_key_exists($offset, $this->rawData);
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @return mixed
	 * @throws Exception
	 */
	public function offsetGet($offset) {
		if (!$this->offsetExists($offset)) {
			throw new Exception("Invalid key '$offset'");
		}
		return $this->rawData[ $offset ];
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		$this->rawData[ $offset ] = $value;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 *                      The offset to unset.
	 *                      </p>
	 * @return void
	 */
	public function offsetUnset($offset) {
		if ($this->offsetExists($offset)) {
			unset($this->rawData[ $offset ]);
		}
	}

}
