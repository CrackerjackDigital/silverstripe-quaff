<?php
namespace Quaff\Transport\Buffers;

use Modular\Debugger;
use Quaff\Exceptions\Transport as Exception;
use Quaff\Transport\Reader;

/**
 * Buffer which copies first to a local file.
 *
 * @package Quaff\Transport\Copiers
 */
trait cached {
	abstract public function copy($fpFrom, $fpTo);

	/**
	 * @param resource $fp
	 * @return Reader
	 */
	abstract public function reader($fp);

	abstract public function readContext();

	abstract public function writeContext();

	abstract public function safePathName($uri);

	/**
	 * @return Debugger
	 */
	abstract public function debugger();

	abstract public function sanitiseURI($uri);

	/**
	 * Copy from one file to another and return a file pointer to the destination file ready to read from start of the file.
	 *
	 * @param string $fromURI        local or remote file
	 * @param string $toFilePathName local file name
	 * @param null   $contentLength  the number of bytes copied
	 * @return mixed
	 */
	public function buffer($fromURI, $toFilePathName, &$contentLength = null) {
		if ($fpTo = fopen($this->safePathName($toFilePathName), 'w+', false, $this->writeContext())) {
			if ($fpFrom = fopen($this->safePathName($fromURI), 'r', false, $this->readContext())) {
				try {
					$contentLength = $this->copy($fpFrom, $fpTo);

					fflush($fpTo);
					fseek($fpTo, 0, SEEK_SET);

					return $this->reader($fpTo);

				} catch (\Exception $e) {
					// tidy up output as something went wrong
					fclose($fpTo);

					$fpTo = null;
					$contentLength = null;

					$this->debugger()->fail("Failed to copy from '" . $this->sanitiseURI($fromURI) . "' to '$toFilePathName'", new Exception());

				} finally {
					// close the input
					fclose($fpFrom);
				}
			} else {
				$this->debugger()->fail("Failed to open source file '" . $this->sanitiseURI($fromURI) . "'", new Exception());
			}
		} else {
			$this->debugger()->fail("Failed to open buffer file '$toFilePathName'", new Exception());
		}
	}

}