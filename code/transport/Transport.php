<?php
namespace Quaff\Transport;

use Modular\Object;
use Quaff\Endpoint;
use Quaff\Interfaces\Transport as TransportInterface;
use Quaff\Exceptions\Transport as Exception;
use Injector;
use DOMDocument;

abstract class Transport extends Object
	implements TransportInterface {
	/**
	 * @param Endpoint $endpoint
	 * @param array         $data
	 * @param array         $options
	 * @return TransportInterface
	 */
	public static function factory(Endpoint $endpoint, array $data = [], array $options = []) {
		$transportClass = $endpoint->getTransportClass();

		return Injector::inst()->create($transportClass, $endpoint, $data, $options);
	}

	/**
	 * Return provided text as json.
	 * @param $bodyText
	 * @return mixed
	 * @throws Exception
	 */
	public function json($bodyText) {
		$json = json_decode($bodyText, true);
		if (json_last_error()) {
			throw new Exception(json_last_error_msg());
		}
		return $json;
	}

	/**
	 * Return provided text as an xml DOM Document.
	 *
	 * @param $bodyText
	 * @return \DOMDocument
	 * @throws Exception
	 */
	public function xml($bodyText) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$doc = new DOMDocument();
		$doc->loadXML($bodyText);

		if ($error = libxml_get_last_error()) {
			throw new Exception($error->message);
		}
		return $doc;
	}

}