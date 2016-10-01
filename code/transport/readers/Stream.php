<?php
namespace Quaff\Transport\Readers;

use Quaff\Transport\Reader;

/**
 * Returns the whole file content from a php stream on read.
 *
 * @package Quaff\Transport\Readers
 */
class Stream extends Reader {
	/** @var resource stream pointer */
	protected $fp;

	/**
	 * Stream constructor.
	 *
	 * @param resource $fp file pointer to read from.
	 */
	public function __construct($fp) {
		parent::__construct();
		$this->fp = $fp;
	}

	public function read() {
		return file_get_contents($this->fp);
	}

	public function close() {
		if ($this->fp) {
			fclose($this->fp);
			$this->fp = null;
		}
	}
}