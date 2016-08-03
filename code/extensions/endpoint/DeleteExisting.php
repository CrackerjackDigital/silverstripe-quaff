<?php

class QuaffDeleteExistingEndpointExtension extends Extension {
	use Modular\enabler;

	/**
	 * @return QuaffEndpointInterface
	 */
	protected function owner() {
		return $this->owner;
	}

	/**
	 * If enabled deletes existing models of the extended endpoint's modelClass.
	 */
	public function beforeQuaff() {
		if ($this->enabled()) {
			DataObject::get($this->owner()->getModelClass())->removeAll();
		}
	}
}