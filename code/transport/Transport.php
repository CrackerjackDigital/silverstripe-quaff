<?php
namespace Quaff\Transport;

use Modular\Object;
use Quaff\Endpoint;
use Quaff\Interfaces\Transport as TransportInterface;

use Injector;

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

}