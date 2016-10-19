<?php
namespace Quaff\Interfaces;

interface Decoder {
	/**
	 * Given encoded data return it decoded for processing (e.g. parse a line into csv columns or a json string into objects).
	 * @param mixed $encoded
	 * @return mixed
	 */
	public function decode($encoded);
}