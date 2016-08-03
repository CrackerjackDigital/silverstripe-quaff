<?php
namespace Quaff;

use Modular\config;
use Modular\owned;
use Quaff\Interfaces\Mappable;

/**
 * Adds a list of fields from the model to the query parameters.
 * e.g fields=id,title,name
 */

class FieldListQueryHelper extends URIHelper {
	use config;
	use owned;

	private static $fields_key = 'fields';

	private static $fields_delimiter = ',';

	/**
	 * Encode model and options on the query string suitable for Arlo API.
	 *
	 * - adds fields=field1,field2 parameter
	 *
	 * @param Mappable|\DataObject|null $model
	 * @param array                                  $params
	 * @return array
	 */
	public function updateQueryParameters(&$params, $model) {
		if ($model) {
			$delimiter = Mapper::path_delimiter();

			$map = $model->quaffMapForEndpoint($this->owner(), Mappable::MapOwnFieldsOnly);

			/* map out the 'api' fields which exist in a remote relationship and return
			   first part of the 'field' e.g. 'Description' for 'Description.Text' so request
				includes the correct list of fields. */

			$fieldNames = array_unique(
				array_map(
					function ($fieldInfo) use ($delimiter) {
						// return the first segment of the remote name from the field map.
						return current(explode($delimiter, $fieldInfo[1]));
					},
					$map
				)
			);
			if ($fieldNames) {
				$params[ (string) static::get_config_setting('fields_key') ] =
					implode(
						(string) static::get_config_setting('fields_delimiter'),
						$fieldNames
					);
			}
		}

	}
}