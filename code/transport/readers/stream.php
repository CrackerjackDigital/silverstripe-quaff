<?php
namespace Quaff\Transport\Readers;

trait stream {
	/**
	 * @return resource stream/file pointer
	 */
	abstract public function getBuffer();

	public function read(&$done = false) {
		if ($stream = $this->getBuffer()) {
			while (!feof($stream)) {
				yield stream_get_contents($stream);
			};
		}
		$done = true;
	}
}