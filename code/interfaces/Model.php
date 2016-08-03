<?php
namespace Quaff\Interfaces;

interface Model {
	const DefaultQuaffOptions = 72; // self::DecodeNone | self::MapDeep
	const DefaultSpoutOptions = 72; // self::DecodeNone | self::MapDeep

	/**
	 * Import data to the model for the endpoint.
	 *
	 * @param Endpoint $endpoint such as 'get/online-activities'
	 * @param array    $data     to be imported via the map found for the endpoint
	 * @param int      $options
	 * @return mixed
	 */
	public function quaff(Endpoint $endpoint, $data, $options = self::DefaultQuaffOptions);

}