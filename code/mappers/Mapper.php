<?php
namespace Quaff\Mappers;

use ClassInfo;
use DataObject;
use Injector;
use Modular\Object;
use Quaff\Exceptions\Mapping;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Locator as LocatorInterface;
use Quaff\Interfaces\Mapper as MapperInterface;
use Quaff\Interfaces\Quaffable;
use Quaff\Exceptions\Mapping as Exception;
use ValidationException;

abstract class Mapper extends Object
	implements MapperInterface, LocatorInterface {
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

	public function __construct(EndpointInterface $endpoint = null) {
		$this->endpoint = $endpoint;
		parent::__construct();
	}

	public function setEndpoint(EndpointInterface $endpoint) {
		$this->endpoint = $endpoint;
		return $this;
	}

	/**
	 * Given data in a nested array, a field map to a flat structure and a DataObject to set field values
	 * on populate the model.
	 *
	 * @param array|string         $fromData
	 * @param DataObject|Quaffable $toModel - model to receive parsed value as field values
	 * @param EndpointInterface    $endpoint
	 * @param int                  $options bitfield of or'd self::OptionXYZ flags
	 * @return int - number of fields found for mapping
	 * @throws Mapping
	 */
	public function quaff($fromData, DataObject $toModel, EndpointInterface $endpoint, $options = Mapper::DefaultOptions) {
		if (!$map = $toModel->quaffMapForEndpoint($endpoint)) {
			throw new Exception("No map found for endpoint '" . $endpoint->getAlias() . "'");
		}
		$numFieldsFound = 0;

		foreach ($map as $fieldInfo) {
			$found = false;

			// data path is the first value in tuple
			$dataPath = $fieldInfo[0];

			$value = static::traverse($dataPath, $fromData, $found);

			if ($found) {
				$this->found($value, $toModel, $fieldInfo, $options);
				$numFieldsFound++;
			} else {
				$this->notFound($toModel, $fieldInfo, $options);
			}
		}
		return $numFieldsFound;
	}

	/**
	 * A value was found so map it to the DataObject.
	 *
	 * @param                           $value
	 * @param DataObject|Quaffable      $toModel
	 * @param                           $fieldInfo
	 * @param  int                      $options bitfield of or'd self::OptionXYZ flags
	 * @throws Mapping
	 * @throws ValidationException
	 * @throws null
	 */
	protected function found($value, DataObject $toModel, $fieldInfo, $options = self::DefaultOptions) {
		list($dataPath, $modelPath, , $isTagField, $method, $relationship) = $fieldInfo;

		$delimiter = static::path_delimiter();

		if ($method) {
			list($dataPath) = explode($delimiter, $dataPath);

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
			$relationshipName = $modelPath;

			if ($isTagField && !self::bitfieldTest($options, self::OptionSkipTagFields)) {

				// setter should map through to a set<RelationshipName> method on the Quaff extension
				if ($toModel->hasMethod("set$relationshipName")) {
					$toModel->$relationshipName = $value;
				}

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
			} elseif ($toModel->hasMethod("set$modelPath")) {

				$toModel->{"set$modelPath"}($value);

			} elseif ($toModel->hasField($modelPath)) {

				$toModel->$modelPath = $value;
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
	 * Return a mapper via depth-first traversal of class heirarchy derived from Mapper
	 * which can handle the provided endpoint's data type (acceptType).
	 *
	 * @param string $acceptType
	 * @return \Quaff\Interfaces\Mapper
	 * @throws Exception
	 */
	public static function locate($acceptType) {
		if ($mapper = static::cache($acceptType)) {
			return $mapper;
		}
		foreach (ClassInfo::subclassesFor(get_called_class()) as $className) {
			if ($className == get_called_class()) {
				continue;
			}
			/** @var Mapper $mapper */
			$mapper = Injector::inst()->create($className);

			// depth-first traversal of class heirarchy
			if (!$sub = $mapper::locate($acceptType)) {
				// try this class
				if ($mapper->match($acceptType)) {
					static::cache($acceptType, $mapper);
					break;
				}
			} else {
				// mapper was found in subclasses
				$mapper = $sub;
			}
		}
		return $mapper;
	}

	/**
	 * Tests if this mapper handles the passed contentType, e.g. application/json
	 *
	 * @param $contentType
	 * @return bool
	 */
	public function match($contentType) {
		return in_array($contentType, $this->getContentTypes());
	}

	/**
	 * Return array of content types this Mapper can map
	 * @return array
	 */
	public function getContentTypes() {
		return $this->config()->get('content_types') ?: [];
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
