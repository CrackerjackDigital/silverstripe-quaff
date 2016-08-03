<?php
use Modular\ModelExtension;

/**
 * Adds a column to track the ordering of the model according to the order it was imported from the API.
 * QuaffOrderableExtension
 */
class QuaffOrderableEndpointExtension extends ModelExtension {
	use Modular\enabler;

	private $order;

	/**
	 * Resets the order count for the model to either the highest existing model order
	 * or the number of existing models. Called when the a particular model is about to be imported from API.
	 */
	public function beforeQuaff($items) {
		$existing = DataObject::get($this->owner()->getModelClass())
			->sort(QuaffOrderableModelExtension::OrderFieldName, 'DESC');

		$this->setQuaffedOrder($existing->count()
			? $existing->limit(1)->first()->{QuaffOrderableModelExtension::OrderFieldName}
				?: 0
			: $existing->count()
		);
	}

	public function afterQuaff($model, $items) {
		$this->order = null;
	}

	/**
	 * Return the current order, optionally and by default incrementing it.
	 *
	 * @param bool $increment
	 * @return mixed
	 */
	public function quaffedOrder($increment = true) {
		if ($increment) {
			return ++$this->order;
		} else {
			return $this->order;
		}
	}

	public function setQuaffedOrder($order) {
		$this->order = $order;
	}

}