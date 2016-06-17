<?php


class QuaffOrderableModelExtension extends ModularDataExtension {
	const OrderFieldName = 'QuaffedOrder';

	private static $db = [
		self::OrderFieldName => 'Int'
	];

	private $quaff_orderable_direction = 'ASC';

	public static function quaff_orderable_direction() {
		return static::config()->get('quaff_orderable_direction');
	}
	/**
	 * Called after each model is loaded from api data to set the quaff order field.
	 *
	 * @param \QuaffEndpointInterface|\QuaffOrderableEndpointExtension $endpoint
	 */
	public function afterQuaff(QuaffEndpointInterface $endpoint) {
		$this->owner->{self::OrderFieldName} = $endpoint->quaffedOrder(true);
	}
}