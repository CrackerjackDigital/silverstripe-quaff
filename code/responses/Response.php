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
use Quaff\Transport\Reader;

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
	// native result code
	protected $resultCode;

	/** @var Reader */
	protected $reader;

	// information about the response
	protected $metaData;

	/** @var EndpointInterface */
	protected $endpoint = null;

	/**
	 * Response constructor.
	 *
	 * @param EndpointInterface $endpoint
	 * @param                   $resultCode
	 * @param Reader            $reader   e.g. body of HTTP response
	 * @param array             $metaData extra information such as headers
	 */
	public function __construct(EndpointInterface $endpoint, $resultCode, $reader, $metaData) {
		$this->endpoint = $endpoint;
		$this->resultCode = $resultCode;
		$this->reader = $reader;
		$this->metaData = $metaData;
		parent::__construct();
	}

	/**
	 * Return metaData value by key if provided or all meta data if no key given
	 *
	 * @param $key
	 * @return array
	 */
	public function meta($key = null, $subkey = null) {
		if (func_num_args()) {
			$value = array_key_exists($key, $this->metaData ?: []) ? $this->metaData[ $key ] : null;

			if (func_num_args() == 1) {
				// no subkey
				return $value;
			}

			if (is_array($value) && array_key_exists($subkey, $value)) {
				// subkey found
				return $value[ $subkey ];
			}

			return null;
		}
		// return all the meta data as no params
		return $this->metaData;
	}

	/**
	 * Return native or implementation defined status code, e.g. '200' for HTTP.
	 *
	 * @return mixed
	 */
	public function getResultCode() {
		return $this->resultCode;
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
