<?php

interface QuaffTransportInterface {
	/**
	 * @param string $uri
	 * @return array|SimpleXMLElement
	 */
	public function get($uri);
}