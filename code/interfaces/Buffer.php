<?php
namespace Quaff\Interfaces;

interface Buffer extends \Iterator {
	/**
	 * Return the uri this buffer was loaded from
	 *
	 * @return string
	 */
	public function getURI();

	/**
	 * Initialise the buffer, open file pointer, connect to database etc.
	 *
	 * @param      $uri
	 * @return mixed
	 */
	public function buffer($uri);

	/**
	 * Read from buffer and return line, whole buffer, a chunk, an object or whatever
	 * @param $responseCode
	 * @return mixed
	 */
	public function read(&$responseCode);

	/**
	 * Open a uri for a particular action (e.g. Transport::ActionRead). Return meta-data about it if possible.
	 *
	 * @param      $uri
	 * @param      $forAction
	 * @param null $responseCode
	 * @param null $contentType
	 * @param null $contentLength
	 * @return mixed
	 */
	public function open($uri, $forAction, &$responseCode = null, &$contentType = null, &$contentLength = null);

	/**
	 * Check URI exists and if possible return meta data about it, otherwise set meta data to null.
	 * @param      $uri
	 * @param null $responseCode
	 * @param null $contentType
	 * @param null $contentLength
	 * @return mixed
	 */
	public function ping($uri, &$responseCode = null, &$contentType = null, &$contentLength = null);

	/**
	 * Close, tidyup etc
	 *
	 * @return $this
	 */
	public function discard();

	public function meta($key = null);

}