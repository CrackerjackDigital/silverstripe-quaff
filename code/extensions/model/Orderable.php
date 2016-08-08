<?php
namespace Quaff\Extensions\Model;

use Quaff\Interfaces\Endpoint;

class Orderable extends Model {
	const OrderFieldName = 'QuaffedOrder';

	private static $db = [
		self::OrderFieldName => 'Int',
	];

	private $quaff_orderable_direction = 'ASC';

	public static function quaff_orderable_direction() {
		return static::config()->get('quaff_orderable_direction');
	}

	/**
	 * Called after each model is loaded from api data to set the quaff order field.
	 *
	 * @param Endpoint|\Quaff\Extensions\Endpoint\Orderable $endpoint
	 */
	public function afterQuaff(Endpoint $endpoint) {
		$this->owner->{self::OrderFieldName} = $endpoint->quaffedOrder(true);
	}
}