<?php
namespace Quaff\Responses;

class OK extends Response {

	public function getVersion() {
		return $this->meta('Version');
	}

	public function isError() {
		return false;
	}

	public function getItemCount() {
		return $this->meta('ItemCount');
	}

	public function getStartIndex() {
		return $this->meta('StartIndex');
	}

	public function getResultMessage() {
		return self::OKMessage;
	}

}