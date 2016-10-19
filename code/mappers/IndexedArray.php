<?php
namespace Quaff\Mappers;
/**
 * A mapper which takes an indexed array as a lookup into the path for traversing, such as for a csv line
 * where the header contains an indexed array of source field names.
 *
 * @package Quaff\Mappers
 */
class IndexedArray extends AssociativeArray {
	/**
	 * AssociativeArray of fields names as found in the source to their column in the data.
	 * @var array
	 */
	private static $index_map = [
		# e.g.
	    # 'First Name' => 1,
	    # 'Last Name' => 2,
	    # 'Date Of Birth' => 5
	];

	/**
	 * @param string $dataPath will be a numeric index of the column
	 * @param array  $data
	 * @param bool   $found
	 * @return array|null|string
	 */
	public static function traverse($dataPath, array $data, &$found = false) {
		$map = static::config()->get('index_map');
		$index = array_search($dataPath, $map);

		if (false !== $index) {
			$dataPath = $index;
		}
		// numerical indexing may be specified though not as clean
		return parent::traverse($dataPath, $data, $found);
	}
}