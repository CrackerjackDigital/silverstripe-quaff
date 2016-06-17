<?php

class QuaffApiErrorResponse extends QuaffAPIResponse {

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