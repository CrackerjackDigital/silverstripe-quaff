<?php
namespace Quaff\Transport;

use Quaff\Exceptions\Transport as Exception;
use Quaff\Responses\Error;

abstract class Stream extends Transport implements StreamInterface {
	const MetaLineNumber = 'LineNumber';

	/**
	 *
	 * Override so we yield a response per line of the file, including one for the header.
	 *
	 * @param string $uri
	 * @param array  $options
	 * @return \Generator
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function get($uri, array $options = []) {
		$nativeOptions = self::native_options(self::ActionRead, $options);

		// check it exists or fail
		if (!$this->ping($uri)) {
			throw new Exception("File '" . $this->sanitise($uri) . "' not found");
		}
		if (!$fp = fopen($uri, 'r', static::context(self::ActionRead, $options))) {
			throw new Exception("Failed to open file for reading");
		}
		$lineNumber = 0;
		try {
			while (!feof($fp)) {
				$lineNumber++;

				if (false === ($buff = $this->buffer($fp))) {
					if (!feof($fp)) {
						throw new Exception("Error reading file line #$lineNumber");
					}
				}

				yield self::make_response(
					$this->getEndpoint(),
					self::ResponseCodeOK,
					$buff,
					[
						self::MetaResolvedURI => $filePathName,
						self::MetaLineNumber  => $lineNumber,
						self::MetaContentType => mime_content_type($filePathName) ?: '',
					]
				);
			}
			fclose($fp);

		} catch (\Exception $e) {
			fclose($fp);

			// turn all other exceptions into an error response

			yield new Error(
				$this->getEndpoint(),
				$e->getCode(),
				$e->getMessage(),
				[
					self::MetaResolvedURI   => "Sanitised: $sanitised",
					self::MetaException     => $e,
					self::MetaResultMessage => $e->getMessage(),
				]
			);

		}
	}

	/**
	 * Read a line as a string from a file.
	 *
	 * @param $fp
	 * @param $options
	 * @return string
	 * @throws \Quaff\Exceptions\Transport
	 */
	public function buffer($fp, $options = []) {
		return fgets($fp);
	}
}