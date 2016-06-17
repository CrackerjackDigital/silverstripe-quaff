<?php

/**
 * Extends a model to add mapping between it's fieldMap and api data
 */
class QuaffMappableExtension extends ModularDataExtension
	implements QuaffMappableInterface {
	use \Modular\config;
	use \Modular\bitfield;


	/**
	 * Import from $data into object for the $endpoint, does not write the model.
	 *
	 * @param QuaffEndpointInterface $endpoint
	 * @param array                  $data
	 * @param int                    $options
	 * @return mixed|null
	 */
	public function quaff(QuaffEndpointInterface $endpoint, $data, $options = self::DefaultQuaffOptions) {
		$result = null;

		/** @var QuaffArrayMapper $mapper */
		if ($mapper = QuaffMapper::locate($endpoint)) {

			// notify the model they're about to be quaffed
			$this->owner()->extend('beforeQuaff', $endpoint, $mapper, $data);

			$result = $mapper->quaff($data, $this->owner(), $this->quaffMapForEndpoint($endpoint), $options);

			// notify the model they're were quaffed
			$this->owner()->extend('afterQuaff', $endpoint, $mapper, $data);

		}
		return $result;
	}

	/**
	 * TODO move to spout module
	 *
	 * Opposite of quaff, exports this object as a json suitable array.
	 *
	 * @param QuaffEndpointInterface $endpoint
	 * @param int                    $options or'd self::OptionABC options
	 * @return array
	 */
	/*
	public function spout(QuaffEndpointInterface $endpoint, $options = self::DefaultSpoutOptions) {
		$data = [];

		if ($mapper = QuaffMapper::locate($endpoint)) {

			$this->owner()->extend('beforeSpout', $mapper, $data);

			$data = $mapper->spout($this->owner(), $this->quaffMapForEndpoint($endpoint), true);

			$this->owner()->extend('afterSpout', $mapper, $data);

		}
		return $data;
	}
	*/

	/**
	 * @return DataObject
	 */
	public function owner() {
		return $this->owner;
	}

	public function toMap() {
		return $this->owner()->toMap();
	}

	public function endpoint($endpoint) {
		return $this->ownerConfigSetting('quaff_map', $endpoint) ?: [];
	}

	public function quaffMapForEndpoint(QuaffEndpointInterface $endpoint, $options = self::MapDeep) {
		$map = $this->get_config_setting(
			'quaff_map',
			$endpoint->getPath(),
			$this->owner()->class
		) ?: [];

		$newMap = [];

		foreach ($map as $remotePath => $localPath) {
			$fieldInfo = self::decode_map($localPath, $remotePath);;
			$newMap[$localPath] = $fieldInfo;
		}

		$this->owner()->extend('quaffUpdateMap', $newMap, $endpoint, $options);
		return $newMap;
	}

	public function quaffMapValuesToFields(array $values, $prefix = '', $suffix = '') {
		foreach ($values as $name => $value) {
			$fieldName = $prefix . $name . $suffix;
			$this->owner()->$fieldName = $value;
		}
	}


	/**
	 * Given local and remote paths for mapping decompose into an array usefull during the mapping process.
	 *
	 * @param string $localPath
	 * @return array [ field name, foreign key name, tag field flag, method ]
	 */
	public static function decode_map($localPath, $remotePath) {
		$tagField = $foreignKey = $method = $relationship = null;

		$delimiter = QuaffMapper::path_delimiter();

		if (false !== strpos($localPath, '.')) {
			// local path is a relationship which should be resolved by the mapper
			$relationship = $localPath;
			list($localPath) = explode($delimiter, $localPath);
		}
		if ('=' == substr($remotePath, 0, 1)) {
			// remote path is a lookup to find the item in the database
			$foreignKey = $remotePath = substr($remotePath, 1);
		}
		if ('[]' == substr($remotePath, -2, 2)) {
			// remote path is a set of tags which should be concatenated to the local path
			$remotePath = substr($remotePath, 0, -2);
			$tagField = $localPath;
		}
		if ('()' == substr($remotePath, -2, 2)) {
			// remote path is a method invocation which should be called with the value by the mapper
			// this may result in a call e.g. to a QuaffMapHelper extension on the model being mapped.
			list($remotePath, $method) = explode($delimiter, substr($remotePath, 0, -2));
			// keep method in name to prevent array key collision across same source going to different fields
			$remotePath .= ".$method";
		}
		return [
			$localPath,
			$remotePath,
			$foreignKey,
			$tagField,
			$method,
		    $relationship
		];
	}

}