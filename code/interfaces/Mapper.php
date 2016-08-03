<?php
namespace Quaff\Interfaces;

use DataObject;

/**
 * Interface for classes which provide mapping between api-native or neutral format and another representation,
 * such as the SilverStripe data model.
 */
interface Mapper {
	const OptionSkipNulls                   = 1;          // update missing api values to null
	const OptionShallow                     = 2;            // don't import relationships if set
	const OptionSkipTagFields               = 4;      // don't import/decode tag fields
	const OptionRemoveObsoleteRelationships = 8;    // remove relationships
	const OptionClearOneToMany              = 16;
	const OptionDeleteOneToMany             = 48;  // delete implies clear so 32 | 16

	const DefaultMapMethodPrefix = 'quaff';

	const DefaultOptions = self::OptionDeleteOneToMany;

	const DefaultPathDelimiter = '.';

	const DefaultTagDelimiter = '|';

	/**
	 * Return an array of acceptTypes this mapper handles.
	 *
	 * @return array
	 */
	public function acceptTypes();

	/**
	 * @param array      $fromData
	 * @param DataObject $toModel
	 * @param array      $fieldMap
	 * @param int        $options
	 * @return mixed
	 */
	public function quaff($fromData, DataObject $toModel, array $fieldMap, $options = self::DefaultOptions);

	/**
	 * @param DataObject|array $fromModelOrArray
	 * @param array            $fieldMap
	 * @param bool             $skipNulls
	 * @return mixed
	 */
	public function spout($fromModelOrArray, array $fieldMap, $skipNulls);

	/**
	 * Looks up $path in data and returns value (setting $found to true if so).
	 *
	 * @param Mapper $mapper
	 * @param array  $fieldInfo
	 * @param bool   $found
	 * @return mixed
	 */
	public static function traverse(Mapper $mapper, array $fieldInfo, array $data, &$found = false);

	/**
	 * Adds value to $data at $path.
	 *
	 * @param Mapper      $mapper
	 * @param             $path
	 * @param             $value
	 * @param array       $data
	 * @return mixed
	 */
	public static function build(Mapper $mapper, $localName, $value, array &$data);
}