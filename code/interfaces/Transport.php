<?php
namespace Quaff\Interfaces;

interface Transport {
	/**
	 * @param string $uri
	 * @return array|\SimpleXMLElement
	 */
	public function get($uri);
}