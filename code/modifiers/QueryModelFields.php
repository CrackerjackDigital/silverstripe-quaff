<?php
namespace Quaff\Modifiers;

use Modular\ModelExtension;
use Modular\Object;
use Quaff\Extensions\Model\Quaffable;
use Quaff\Interfaces\Endpoint;

/**
 * Endpoint Extension that given a model template or using the extended endpoints returned model class adds fields from the model to the query parameters,
 * e.g. for selecting the fields which will be returned
 *
 * TODO FINISH AND TEST IT!!!
 *
 * only want to add fields either all from model, no values and e.g. csv seperated, or from the info.fields collection if present
 *
 * @package Quaff\Modifiers
 */
class QueryModelFields extends ModelExtension {
	/**
	 * Adds fields and their values as parameters. If no model is supploed
	 *
	 * TODO TEST IT!!!
	 *
	 * @param array                            $params
	 * @param \Quaff\Interfaces\Quaffable|null $model
	 */
	public function updateQueryParameters(array &$params, \Quaff\Interfaces\Quaffable $model = null) {
		$fields = [];

		$info = $this->endpoint()->info('fields');

		if (!$model) {
			$model = singleton($this->endpoint()->getModelClass());
		}

		if ($model) {
			// TODO handle recursive mapping for arrays/collections.
			// add model fields which are in the field map to the parameters as a name=value entry.
			$fields += array_filter(
				array_intersect_key(
					$model->toMap(),
					array_flip(
						array_keys(
							$model->quaffMapForEndpoint(
								$this->endpoint(),
								Quaffable::MapOwnFieldsOnly
							)
						)
					)
				),
				function ($value) {
					return urlencode(trim($value));
				}
			);
			foreach ($fields as $name => $value) {
				$params[ $name ] = $value;
			}
		}
	}

	/**
	 * @return Endpoint
	 */
	protected function endpoint() {
		return $this();
	}
}