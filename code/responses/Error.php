<?php

namespace Quaff\Responses;

class Error extends Response {

	public function getItems() {
		return null;
	}

	public function getVersion() {
		return $this->meta('Version');
	}

	public function isError() {
		return true;
	}

	public function getItemCount() {
		return 0;
	}

	public function getStartIndex() {
		return null;
	}
}