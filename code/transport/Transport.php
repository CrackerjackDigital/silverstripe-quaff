<?php
use \Modular\ModularObject as Object;

abstract class QuaffTransport extends Object
	implements QuaffTransportInterface
{
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
}