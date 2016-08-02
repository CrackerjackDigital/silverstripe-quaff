<?php
use \Modular\ModularObject as Object;

abstract class QuaffTransport extends Object
	implements QuaffTransportInterface {
	/**
	 * @param QuaffEndpoint $endpoint
	 * @param array         $data
	 * @param array         $options
	 * @return QuaffTransportInterface
	 */
	public static function factory(QuaffEndpoint $endpoint, array $data = [], array $options = []) {
		$transportClass = $endpoint->getTransportClass();

		return Injector::inst()->create($transportClass, $endpoint, $data, $options);
	}

	/**
	 * Return provided text as json.
	 * @param $bodyText
	 * @return mixed
	 * @throws \QuaffTransportException
	 */
	public function json($bodyText) {
		$json = json_decode($bodyText, true);
		if (json_last_error()) {
			throw new QuaffTransportException(json_last_error_msg());
		}
		return $json;
	}

	/**
	 * Return provided text as an xml DOM Document.
	 *
	 * @param $bodyText
	 * @return \DOMDocument
	 * @throws \QuaffTransportException
	 */
	public function xml($bodyText) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$doc = new DOMDocument();
		$doc->loadXML($bodyText);

		if ($error = libxml_get_last_error()) {
			throw new QuaffTransportException($error->message);
		}
		return $doc;
	}

}