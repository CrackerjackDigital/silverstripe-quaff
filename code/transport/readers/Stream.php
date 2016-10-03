<?php
namespace Quaff\Transport\Readers;

use Quaff\Transport\Reader;

/**
 * Returns the whole file content from a php stream on read.
 *
 * @package Quaff\Transport\Readers
 */
abstract class Stream extends Reader {
	/** @var resource stream pointer */
	protected $stream;

	abstract public function read();

	public function done() {
		return ($this->stream && !feof($this->stream)) || (!$this->stream);
	}

	/**
	 * Stream constructor.
	 *
	 * @param resource $stream file pointer to read from.
	 */
	public function __construct($stream) {
		parent::__construct();
		$this->stream = $stream;
	}

	public function close() {
		if ($this->stream) {
			fclose($this->stream);
			$this->stream = null;
		}
	}
}
