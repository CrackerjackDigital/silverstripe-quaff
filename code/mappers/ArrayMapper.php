<?php
namespace Quaff\Mappers;

use DataObject;
use Quaff\Interfaces\Mapper as MapperInterface;

/**
 * Maps between SilverStripe model and arrays.
 */
class ArrayMapper extends Mapper {
	private static $content_types = [
		'application/json'
	];

	/**
	 * Returns an array build as a nested structure mapping flat values in the array or DataObject passed
	 * to a nested array structure using the provided fieldMap (essentially the reveres of
	 * map_to_model which is easier to understand).
	 * e.g. with map 'RateTitle' => 'rate.summary.title' and data['RateTitle'] = 'Fred' output
	 * with be
	 *  array(
	 *      'rate' => array(
	 *          'summary' => array(
	 *              'title' => 'Fred'
	 *          )
	 *      )
	 *  )
	 *
	 * @param                        $fromModelOrArray - flat source key/value pairs, e.g. from DataObject.toMap
	 * @param                        $fieldMap         - map of source keys to output structure with '.' syntax
	 * @param                        $skipNulls        - if value not in $data or null don't include in output array
	 * @return array
	 */
	public function spout($fromModelOrArray, array $fieldMap, $skipNulls) {
		if ($fromModelOrArray instanceof DataObject) {
			$fromModelOrArray = $fromModelOrArray->toMap();
		}

		$data = array();

		foreach ($fieldMap as $localName => $path) {

			if ((!$skipNulls) || array_key_exists($localName, $fromModelOrArray)) {

				self::build($this, $path, $fromModelOrArray[ $localName ], $data);

			}
		}

		return $data;

	}

	/**
	 * Traverse the array data with a path like 'item.summary.title' in $data and return the value found at the end, if
	 * any.
	 *
	 * @param string          $dataPath
	 * @param array           $data
	 * @param bool            $found - set to true if found, false otherwise
	 * @return array|null|string
	 */
	public static function traverse($dataPath, array $data, &$found = false) {
		$found = false;

		$segments = explode(self::path_delimiter(), $dataPath);

		$pathLength = count($segments);
		$parsed = 0;

		while ($segment = array_shift($segments)) {
			$lastData = $data;

			if (is_numeric($segment)) {
				// array index
				if (isset($lastData[ $segment ])) {
					$data = $lastData[ $segment ];
				}
				$found = true;
				break;

			} elseif (isset($data[ $segment ])) {
				$data = $data[ $segment ];
				$parsed++;

			} else {
				// failed to walk the full path, break out
				break;
			}
			$found = $parsed === $pathLength;
		}

		return $found ? $data : null;
	}

	/**
	 * Add a value to $data at path specified by $path.
	 *
	 * @param MapperInterface $mapper
	 * @param array|string    $path
	 * @param                 $value
	 * @param array           $data
	 * @return mixed|void
	 */
	public static function build(MapperInterface $mapper, $path, $value, array &$data) {
		if (!is_array($path)) {
			$path = explode(static::path_delimiter(), $path);
		}

		$pathLength = count($path);
		$parsed = 1;

		while ($part = array_shift($path)) {
			if (!isset($data[ $part ])) {
				if ($parsed === $pathLength) {

					$data[ $part ] = $value;

				} elseif (!array_key_exists($part, $data)) {

					$data[ $part ] = array();

				}
			}
			$parsed++;
		}

	}

}