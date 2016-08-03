<?php
namespace Quaff;

use Extension;
use Modular\enabler;
use Modular\Model;

class DeleteExistingEndpointExtension extends Extension {
	use enabler;

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
			Model::get($this->owner()->getModelClass())->removeAll();
		}
	}
}