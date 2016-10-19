<?php
namespace Quaff\Transport\Buffers;

use Quaff\Transport\Stream;
use Quaff\Exceptions\Transport as Exception;

trait passthru {
	private $stream;

	abstract public function native_options($forWhat, $options = []);

	abstract public function ping($uri, &$responseCode, &$contentType = null, &$contentLength = null);

	/**
	 * @return resource stream/file pointer
	 */
	public function getBuffer() {
		return $this->stream;
	}

	/**
	 * This buffer doesn't buffer, it just returns a file pointer to the open stream
	 *
	 * @param      $uri
	 * @param      $responseCode
	 * @param null $contentType
	 * @param null $contentLength
	 * @return resource stream/file pointer
	 */
	public function buffer($uri, &$responseCode, &$contentType = null, &$contentLength = null) {
		$this->close();

		if ($this->ping($uri, $responseCode, $contentType, $contentLength)) {
			$context = $this->native_options(Stream::ActionRead);
			$this->stream = fopen($uri, 'r', false, $context);
		}
		return $this->stream;
	}

	/**
	 * @throws \Quaff\Exceptions\Transport
	 * @fluent
	 */
	public function rewind() {
		if (stream_is_local($this->stream)) {
			fseek($this->stream, 0);
			return $this;
		} else {
			throw new Exception("Probably can't rewind a remote stream");
		}
	}

	/**
	 * @return $this
	 * @fluent
	 */
	public function close() {
		if ($this->stream) {
			fclose($this->stream);
			$this->stream = null;
		}
		return $this;
	}
}