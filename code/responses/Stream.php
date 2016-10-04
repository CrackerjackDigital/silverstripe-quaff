<?php
namespace Quaff\Responses;

abstract class Stream extends OK {

	/**
	 * @return resource stream/file pointer
	 */
	public function getBuffer() {
		return $this->getRawData();
	}
}