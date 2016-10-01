<?php
namespace Quaff\Transport\Copiers;

use Quaff\Exceptions\Transport as Exception;

trait chunked {
	abstract public function copyChunkSize();

	public function copy($fpFrom, $fpTo) {
		$totalWritten = 0;
		$chunkSize = $this->copyChunkSize();

		while (!feof($fpFrom)) {
			if (false !== ($chunk = fread($fpFrom, $chunkSize))) {

				$written = fwrite($fpTo, $chunk);
				if (false === $written) {
					throw new Exception("Failed to write to output file");
				}
				$totalWritten += $written;

			} else {
				if (!feof($fpFrom)) {
					throw new Exception("Failed to read from input file during copy process");
				}
			}
		}

		fflush($fpTo);
		fseek($fpTo, 0, SEEK_SET);

	}
}