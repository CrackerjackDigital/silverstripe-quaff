<?php
namespace Quaff\Responses;

use ArrayList;
use Modular\Exceptions\NotImplemented;
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

	const GenericOKMessage   = 'OK';
	const GenericFailMessage = 'Failed (no message available)';

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
	public function __construct(EndpointInterface $endpoint, $rawData, $metaData) {
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
		return $this->meta('ResultMessage') ?: self::GenericFailMessage;
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
	 * @return EndpointInterface
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}

	/**
	 * Return metaData value by key
	 *
	 * @param $key
	 * @return array
	 */
	public function meta($key = null) {
		if (func_num_args()) {
			return array_key_exists($key, $this->metaData ?: []) ? $this->metaData[ $key ] : null;
		}
		return $this->meta;
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


}
