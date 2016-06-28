<?php

/**
 * Interface to add to DataObjects which support Quaff mapping. This is not declared directly on models as it is
 * implemented by QuaffMappableExtension , however is useful to use as a return type or parameter type hint.
 */
interface QuaffMappableInterface extends QuaffModelInterface {
	const EncodeNone = 1;       // don't change values
	const EncodeJSON = 2;       // encode values for json
	const EncodeURL  = 4;        // encode values using urlencode
	const DecodeNone = 8;       // don't decode values
	const DecodeJSON = 16;      // decode from json
	const DcodeURL   = 32;        // decode using urldecode

	const MapDeep          = 64;
	const MapOwnFieldsOnly = 128;

	const DefaultQuaffOptions = 72; // self::DecodeNone | self::MapDeep
	const DefaultSpoutOptions = 72; // self::DecodeNone | self::MapDeep

	/**
	 * From DataObject but we use it so declare it
	 *
	 * @return array
	 */
	public function toMap();

	/**
	 * Returns the map for a given endpoint.
	 *
	 * @param QuaffEndpointInterface $endpoint
	 * @param int                    $options
	 * @return
	 */
	public function quaffMapForEndpoint(QuaffEndpointInterface $endpoint, $options = self::MapDeep);

	/**
	 * Import data to the model for the endpoint.
	 *
	 * @param QuaffEndpointInterface $endpoint such as 'get/online-activities'
	 * @param array                  $data     to be imported via the map found for the endpoint
	 * @param int                    $options
	 * @return mixed
	 */
	public function quaff(QuaffEndpointInterface $endpoint, $data, $options = self::DefaultQuaffOptions);

	/**
	 * TODO move to spout module
	 * Return an array of data mapped via the map found for the provided endpoint.
	 *
	 * @param QuaffEndpointInterface $endpoint
	 * @param int                    $options
	 * @return array
	 */
//	public function spout(QuaffEndpointInterface $endpoint, $options = self::DefaultSpoutOptions);
}