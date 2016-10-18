<?php
namespace Quaff\Interfaces;

interface Protocol {
	/**
	 * Return all meta data or a specific type of meta data (e.g. key = Transport::MetaContentType).
	 * @param null $key
	 * @return mixed
	 */
	public function meta($key = null);
}