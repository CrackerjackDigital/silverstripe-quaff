<?php
namespace Quaff\Transport\Buffers;

use Modular\Debugger;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Transport\Reader;

/**
 * Buffer which doesn't buffer, just returns an open file handle for the source file.
 *
 * @package Quaff\Transport\Copiers
 */
trait passthru {
	abstract public function readContext();

	abstract public function safePathName($uri);

	/**
	 * @return Debugger
	 */
	abstract public function debugger();

	abstract public function sanitiseURI($uri);

	/**
	 * @param resource $fp
	 * @return Reader
	 */
	abstract public function reader($fp);

	public function buffer($fromURI, $toFilePathName, &$contentLength = null) {
		if (!$fp = fopen($this->safePathName($fromURI), 'r', false, $this->readContext())) {
			$this->debugger()->fail("Failed to open source file '" . $this->sanitiseURI($fromURI) . "'", new Exception());
		}
		return $this->reader($fp);
	}
}
