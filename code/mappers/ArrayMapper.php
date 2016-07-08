<?php

/**
 * Maps between SilverStripe model and arrays.
 */
class QuaffArrayMapper extends QuaffMapper {
	private static $accept_types = [];

	/**
	 * Given data in a nested array, a field map to a flat structure and a dataobject to set field values
	 * on populate the model.
	 *
	 * @param array|string $fromData
	 * @param DataObject   $toModel - model to receive parsed value as field values
	 * @param array        $fieldMap
	 * @param int          $options bitfield of or'd self::OptionXYZ flags
	 * @return int - number of fields found for mapping
	 * @throws QuaffMappingException
	 */
	public function quaff($fromData, DataObject $toModel, array $fieldMap, $options = self::DefaultQuaffOptions) {
		$fromData = $this->decode($fromData);
		$numFound = 0;

		foreach ($fieldMap as $localName => $fieldInfo) {

			$value = self::traverse($this, $fieldInfo, $fromData, $found);

			if ($found) {
				$this->found($value, $toModel, $fieldInfo, $options);
				$numFound++;
			} else {
				$this->notFound($toModel, $fieldInfo, $options);
			}
		}
		return $numFound;
	}

	/**
	 * A value was found so map it to the DataObject.
	 *
	 * @param                           $value
	 * @param DataObject|QuaffMapHelper $toModel
	 * @param                           $fieldInfo
	 * @param  int                      $options bitfield of or'd self::OptionXYZ flags
	 * @throws QuaffMappingException
	 * @throws ValidationException
	 * @throws null
	 */
	protected function found($value, DataObject $toModel, $fieldInfo, $options = self::DefaultQuaffOptions) {
		list($localPath, $remotePath, $foreignKey, $isTagField, $method, $relationship) = $fieldInfo;

		$delimiter = static::path_delimiter();

		if ($method) {
			list($localPath) = explode($delimiter, $localPath);

			if ($toModel->hasMethod('quaffMap')) {
				$internallyHandled = false;
				// TODO figure out why can't pass internallyHandled by reference
				$value = $toModel->quaffMap($method, $value, $fieldInfo);
				if ($internallyHandled) {
					return;
				}
			}
		}

		if (is_array($value)) {
			$relationshipName = $localPath;

			if ($isTagField && !self::bitfieldTest($options, self::OptionSkipTagFields)) {

				$toModel->$relationshipName = implode(self::tag_delimiter(), $value);

			} elseif (!self::bitfieldTest($options, self::OptionShallow)) {

				if ($relatedClass = $toModel->has_many($relationshipName)) {
					// add has_many related objects as new objects

					if ($this->bitfieldTest($options, self::OptionDeleteOneToMany)) {
						/** @var DataObject $related */
						foreach ($toModel->$relationshipName() as $related) {
							$related->delete();
						}
					}
					if ($this->bitfieldTest($options, self::OptionClearOneToMany)) {
						// does this go to zero if related records are all deleted?
						$numRelated = $toModel->$relationshipName()->count();
						// delete related records first
						$toModel->$relationshipName()->removeAll();
					}
					foreach ($value as $foreignData) {
						// add a new foreign model to this one.

						/** @var DataObject|QuaffMappableInterface $foreignModel */
						$foreignModel = new $relatedClass();
						$foreignModel->quaff($this->endpoint, $foreignData, $options);
						$foreignModel->write(true);

						$toModel->$relationshipName()->add($foreignModel);
					}

				} elseif ($relatedClass = $toModel->many_many($relationshipName)) {

					// TODO finish of many_many mapping
				}
			}
		} else {
			// handle one-to-one relationship with a lookup field (could be the id or another field, e.g a 'Code' field).
			if ($relationship) {
				list($relationshipName, $lookupFieldName) = explode($delimiter, $relationship);

				if ($relatedClass = $toModel->has_one($relationshipName)) {
					/** @var DataObject $relatedModel */
					$relatedModel = $relatedClass::get()->filter($lookupFieldName, $value)->first();

					if ($relatedModel) {
						$idField = $relationshipName . 'ID';

						$toModel->{$idField} = $relatedModel->ID;

						// TODO validate this works, as in what if there are more than one?
						$backRelationships = $relatedModel->has_many();
						array_map(
							function ($relationshipName, $className) use ($toModel, $relatedModel) {
								if ($className == $toModel->class) {
									$relatedModel->$relationshipName()->add($toModel);
								}
							},
							array_keys($backRelationships),
							array_values($backRelationships)
						);
					}
				}
			} elseif ($toModel->hasField($localPath)) {
				$toModel->$localPath = $value;
			}
		}
	}

	protected function notFound(DataObject $toModel, $fieldInfo, $options) {
		if (!$this->bitfieldTest($options, self::OptionSkipNulls)) {

			list($relationshipOrLocalName, $remoteName, $foreignKey) = $fieldInfo;

			if ($foreignKey && $this->bitfieldTest($options, self::OptionRemoveObsoleteRelationships)) {
				if ($toModel->has_one($relationshipOrLocalName)) {
					// TODO: remove foreign key relationships

				} elseif ($toModel->has_many($relationshipOrLocalName)) {
					// TODO: remove foreign key relationships

				} elseif ($toModel->many_many($relationshipOrLocalName)) {
					// TODO: remove foreign key relationships

				}
			} else {
				$toModel->$relationshipOrLocalName = null;
			}
		}
	}

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

	private function parseRelationship($name) {
		return explode('.', $name);
	}

	/**
	 * Given an array just returns it otherwise returns json_decode's data.
	 *
	 * @param $arrayOrString
	 *
	 * @return array
	 */
	protected function decode(&$arrayOrString) {
		if (!is_array($arrayOrString)) {
			return json_decode($arrayOrString, true);
		} else {
			return $arrayOrString;
		}
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	protected function encode(array $data) {
		return json_encode($data);
	}

	/**
	 * Traverse the array data with a path like 'item.summary.title' in $data and return the value found at the end, if
	 * any.
	 *
	 * @param QuaffMapper $mapper
	 * @param array       $fieldInfo
	 * @param array       $data
	 * @param bool        $found - set to true if found, false otherwise
	 * @return array|string|null
	 */
	public static function traverse(QuaffMapper $mapper, array $fieldInfo, array $data, &$found = false) {
		list($localPath, $remotePath, $foreignKey, $isTagField, $method, $relationship) = $fieldInfo;

		$segments = explode(self::path_delimiter(), $remotePath);

		$pathLength = count($segments);
		$parsed = 0;

		$found = false;

		while ($segment = array_shift($segments)) {
			$lastData = $data;

			if (is_numeric($segment) || $method) {
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
	 * @param QuaffMapper  $mapper
	 * @param array|string $path
	 * @param              $value
	 * @param array        $data
	 * @return mixed|void
	 */
	public static function build(QuaffMapper $mapper, $path, $value, array &$data) {
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