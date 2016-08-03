<?php

namespace Quaff\Responses;

class Error extends Response {

	public function getResponseVersion() {
		return $this->data('Version');
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