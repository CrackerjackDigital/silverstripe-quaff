<?php
namespace Quaff\Interfaces;

interface Transport {
	/**
	 * @param string $uri
	 * @param array  $params
	 * @return array|\SimpleXMLElement
	 */
	public function get($uri, array $params = []);
}