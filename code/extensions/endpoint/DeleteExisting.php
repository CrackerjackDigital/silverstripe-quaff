<?php
namespace Quaff\Extensions\Endpoint;

use Modular\enabler;
use Modular\Model;
use Modular\owned;

class DeleteExisting extends Endpoint {
	use enabler;
	use owned;

	/**
	 * If enabled deletes existing models of the extended endpoint's modelClass.
	 */
	public function beforeQuaff() {
		if ($this->enabled()) {
			Model::get($this->owner()->getModelClass())->removeAll();
		}
	}
}