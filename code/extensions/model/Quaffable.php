<?php
/**
 * Extends a model to add mapping between it's fieldMap and api data
 */
namespace Quaff\Extensions\Model;

use Modular\bitfield;
use Modular\config;
use Modular\ModelExtension;
use Modular\owned;
use Quaff\Endpoints\Endpoint;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Quaffable as QuaffableInterface;
use Quaff\Mappers\Mapper;

class Quaffable extends ModelExtension
	implements QuaffableInterface {
	use config;
	use bitfield;
	use owned;

	/**
	 * Import from $data into object for the $endpoint, does not write the model.
	 *
	 * @param EndpointInterface $endpoint
	 * @param array             $data
	 * @param int               $options
	 * @return mixed|null
	 */
	public function quaff(EndpointInterface $endpoint, $data, $options = self::DefaultQuaffOptions) {
		$result = null;

		/** @var Mapper $mapper */
		if ($mapper = Mapper::locate($endpoint)) {

			// notify the model they're about to be quaffed
			$this->owner()->extend('beforeQuaff', $endpoint, $mapper, $data);

			$result = $mapper->quaff($data, $this->owner(), $endpoint, $options);

			// notify the model they're were quaffed
			$this->owner()->extend('afterQuaff', $endpoint, $mapper, $data);

		}
		return $result;
	}

	public function toMap() {
		return $this->owner()->toMap();
	}

	public function quaffMapForEndpoint(EndpointInterface $endpoint, $options = self::MapDeep) {
		$maps = $this->owner()->config()->get('quaff_map');
		$alias = $endpoint->getAlias();
		$map = [];

		foreach ($maps as $match => $map) {
			if (Endpoint::match($match, $alias)) {
				break;
			}
			$map = [];
		}
		$newMap = [];

		if ($map) {
			foreach ($map as $dataPath => $modelPath) {
				$fieldInfo = self::decode_map($dataPath, $modelPath);
				$newMap[ $modelPath ] = $fieldInfo;
			}
		}

		$this->owner()->extend('quaffUpdateMap', $newMap, $endpoint, $options);
		return $newMap;
	}

	/**
	 * Given local and remote paths for mapping decompose into an array usefull during the mapping process.
	 *
	 * @param string $dataPath  in incoming data, e.g. a dot path on the left of a quaff_map configuration map
	 * @param string $modelPath in SilverStripe e.g a field name on the right of a quaff_map
	 * @return array see comments on return array
	 */
	public static function decode_map($dataPath, $modelPath) {
		$foreignKey = $tagField = $method = $relationship = null;

		$delimiter = Mapper::path_delimiter();

		if (false !== strpos($modelPath, '.')) {
			// model path is a relationship which should be resolved by the mapper
			$relationship = $modelPath;
			list($modelPath) = explode($delimiter, $modelPath);
		}

		if ('=' == substr($dataPath, 0, 1)) {
			// remote path is a lookup to find the item in the database
			$foreignKey = $dataPath = substr($dataPath, 1);
		}
		if ('[]' == substr($dataPath, -2, 2)) {
			// remote path is a set of tags which should be concatenated to the local path
			$dataPath = substr($dataPath, 0, -2);
			$tagField = $modelPath;
		}
		if ('()' == substr($dataPath, -2, 2)) {
			// remote path is a method invocation which should be called with the value by the mapper
			// this may result in a call e.g. to a QuaffMapHelper extension on the model being mapped.
			list($dataPath, $method) = explode($delimiter, substr($dataPath, 0, -2));
			// keep method in name to prevent array key collision across same source going to different fields
			$dataPath .= ".$method";
		}
		return [
			$dataPath,          // processed path in the api data
			$modelPath,         // processed path in the model (a field name)
			$foreignKey,        // set if search field used to match existing models
			$tagField,          // set of tags to concatenate
			$method,            // method to call for this field
			$relationship,      // relationship to use for this field
		];
	}

	/**
	 * Set fields on the extended model from the values, optionally prepending and appending prefix and suffix respectively to the field name being set.
	 *
	 * @param array  $values
	 * @param string $prefix
	 * @param string $suffix
	 */
	public function quaffMapValuesToFields(array $values, $prefix = '', $suffix = '') {
		foreach ($values as $name => $value) {
			$fieldName = $prefix . $name . $suffix;
			$this->owner()->$fieldName = $value;
		}
	}

}