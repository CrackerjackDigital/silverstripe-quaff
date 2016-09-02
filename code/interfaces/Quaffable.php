<?php
namespace Quaff\Interfaces;

/**
 * Interface to add to DataObjects which support Quaff mapping. This is not declared directly on models as it is
 * implemented by QuaffMappableExtension , however is useful to use as a return type or parameter type hint.
 */
interface Quaffable {
	const EncodeNone = 1;       // don't change values
	const EncodeJSON = 2;       // encode values for json
	const EncodeURL  = 4;        // encode values using urlencode
	const DecodeNone = 8;       // don't decode values
	const DecodeJSON = 16;      // decode from json
	const DecodeURL  = 32;        // decode using urldecode

	const MapDeep          = 64;
	const MapOwnFieldsOnly = 128;

	const DefaultQuaffOptions = 72; // self::DecodeNone | self::MapDeep

	/**
	 * From DataObject but we use it so declare it
	 *
	 * @return array
	 */
	public function toMap();

	/**
	 * Import data to the model for the endpoint.
	 *
	 * @param Endpoint $endpoint such as 'get/online-activities'
	 * @param array    $data     to be imported via the map found for the endpoint
	 * @param int      $options
	 * @return mixed
	 */
	public function quaff(Endpoint $endpoint, $data, $options = self::DefaultQuaffOptions);

	/**
	 * Returns the map for a given endpoint for the extended model.
	 *
	 * @param Endpoint $endpoint
	 * @param int      $options
	 * @return array
	 */
	public function quaffMapForEndpoint(Endpoint $endpoint, $options = self::MapDeep);

}