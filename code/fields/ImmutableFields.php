<?php
namespace Quaff\Fields;

use Modular\Fields\Field;

/**
 * Field or fields which are imported from api and connot be changed in the CMS.
 *
 * @package Quaff\Fields
 */
class ImmutableFields extends Field {

	/**
	 * Adds all db fields defined on this extension (subclass) as ReadOnlyFields.
	 */

	public function cmsFields() {
		$fieldDefs = \Config::inst()->get(get_called_class(), 'db') ?: [];
		$fields = [];
		foreach ($fieldDefs as $fieldName => $schema) {
			$fields[] = (new \ReadonlyField(
				$fieldName
			))->setRightTitle(
				$this->fieldDecoration(
					$fieldName,
					'Guide',
					"imported from api {api_field_name}",
					[
						'api_field_name' => $this->apiFieldName($fieldName)
					]
				)
			);
		}
	}

	/**
	 * Override to provide api field name for passed local field name if can be determined.
	 *
	 * TODO figure out how to do this nicely and/or generically from e.g. quaff_map when we don't have the endpoint
	 *
	 * @param $localFieldName
	 * @return string
	 */
	protected function apiFieldName($localFieldName) {
		return '';
	}
}