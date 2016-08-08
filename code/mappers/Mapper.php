<?php
namespace Quaff\Mappers;

use ClassInfo;
use DataObject;
use Injector;
use Modular\Object;
use Quaff\Exceptions\Exception;
use Quaff\Exceptions\Mapping;
use Quaff\Extensions\Mapping\Helper;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Mapper as MapperInterface;
use Quaff\Interfaces\Quaffable;
use ValidationException;

abstract class Mapper extends Object
	implements MapperInterface {
	// set this so all method's to call on model for value resolution  are prefixed by this,
	// e.g. 'quaff' for 'quaffURLSegment' if method is 'URLSegment'
	private static $map_method_prefix = self::DefaultMapMethodPrefix;

	private static $path_delimiter = self::DefaultPathDelimiter;

	private static $tag_delimiter = self::DefaultTagDelimiter;

	/** @var  EndpointInterface */
	protected $endpoint;

	private static $use_cache = true;

	/** @var array of content types this mapper can handle, e.g. [ 'application/json' ] or [ 'application/xml', 'text/xml' ] */
	private static $content_types = [];

	public function __construct(EndpointInterface $endpoint) {
		$this->endpoint = $endpoint;
		parent::__construct();
	}

	/**
	 * Given data in a nested array, a field map to a flat structure and a DataObject to set field values
	 * on populate the model.
	 *
	 * @param array|string $fromData
	 * @param DataObject   $toModel  - model to receive parsed value as field values
	 * @param array        $fieldMap of data path to model path
	 * @param int          $options  bitfield of or'd self::OptionXYZ flags
	 * @return int - number of fields found for mapping
	 * @throws Mapping
	 */
	public function quaff($fromData, DataObject $toModel, array $fieldMap, $options = Mapper::DefaultOptions) {
		$fromData = $this->decode($fromData);
		$numFound = 0;

		foreach ($fieldMap as $fieldInfo) {
			$found = false;

			// data path is the first value in tuple
			$dataPath = $fieldInfo[0];

			$value = static::traverse($dataPath, $fromData, $found);

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
	 * A value was found so map it to the DataObject.
	 *
	 * @param                           $value
	 * @param DataObject|Helper         $toModel
	 * @param                           $fieldInfo
	 * @param  int                      $options bitfield of or'd self::OptionXYZ flags
	 * @throws Mapping
	 * @throws ValidationException
	 * @throws null
	 */
	protected function found($value, DataObject $toModel, $fieldInfo, $options = self::DefaultOptions) {
		list($localPath, , , $isTagField, $method, $relationship) = $fieldInfo;

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

				if ($relatedClass = $toModel->hasMany($relationshipName)) {
					// add has_many related objects as new objects

					if ($this->bitfieldTest($options, self::OptionDeleteOneToMany)) {
						/** @var DataObject $related */
						foreach ($toModel->$relationshipName() as $related) {
							$related->delete();
						}
					}
					if ($this->bitfieldTest($options, self::OptionClearOneToMany)) {
						// remove related records first
						$toModel->$relationshipName()->removeAll();
					}
					foreach ($value as $foreignData) {
						// add a new foreign model to this one.

						/** @var DataObject|Quaffable $foreignModel */
						$foreignModel = new $relatedClass();
						$foreignModel->quaff($this->endpoint, $foreignData, $options);
						$foreignModel->write(true);

						$toModel->$relationshipName()->add($foreignModel);
					}

				} elseif ($relatedClass = $toModel->manyMany($relationshipName)) {

					// TODO finish of many_many mapping
				}
			}
		} else {
			// handle one-to-one relationship with a lookup field (could be the id or another field, e.g a 'Code' field).
			if ($relationship) {
				list($relationshipName, $lookupFieldName) = explode($delimiter, $relationship);

				if ($relatedClass = $toModel->hasOne($relationshipName)) {
					/** @var DataObject $relatedModel */
					$relatedModel = $relatedClass::get()->filter($lookupFieldName, $value)->first();

					if ($relatedModel) {
						$idField = $relationshipName . 'ID';

						$toModel->{$idField} = $relatedModel->ID;

						// TODO validate this works, as in what if there are more than one?
						$backRelationships = $relatedModel->hasMany();
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

			list(, $modelPath, $foreignKey) = $fieldInfo;

			if ($foreignKey && $this->bitfieldTest($options, self::OptionRemoveObsoleteRelationships)) {
				if ($className = $toModel->hasOneComponent($modelPath)) {
					// TODO: remove foreign key relationships

				} elseif ($className = $toModel->hasMany($modelPath)) {
					// TODO: remove foreign key relationships

				} elseif (list(, $className) = $toModel->manyManyComponent($modelPath)) {

					// TODO: remove foreign key relationships

				}
			} else {
				$toModel->$modelPath = null;
			}
		}
	}

	/**
	 * Return a mapper which can handle the provided endpoint's data type (acceptType).
	 *
	 * @param EndpointInterface $endpoint
	 *
	 * @return MapperInterface
	 * @throws Exception
	 */
	public static function locate(EndpointInterface $endpoint) {
		$acceptType = $endpoint->getAcceptType();

		$mapper = static::cache($acceptType);

		if (!$mapper) {
			foreach (array_slice(ClassInfo::subclassesFor('Quaff\Mapper'), 1) as $className) {
				/** @var Mapper $mapper */
				$mapper = Injector::inst()->create($className, $endpoint);

				if (static::match_content_type($acceptType)) {
					break;
				}

				$mapper = null;
			}
		}
		return static::cache($acceptType, $mapper);
	}

	/**
	 * Tests if the provided acceptType is in the array of acceptTypes.
	 *
	 * @param $contentType
	 * @return bool
	 */
	public static function match_content_type($contentType) {
		return in_array($contentType, static::config()->get('content_types'));
	}

	public function contentTypes() {
		return $this->config()->get('content_types');
	}

	/**
	 * Convenience.
	 *
	 * @return string
	 */
	public static function path_delimiter() {
		return static::config()->get('path_delimiter');
	}

	/**
	 * Convenience.
	 *
	 * @return string
	 */
	public static function tag_delimiter() {
		return static::config()->get('tag_delimiter');
	}

}
