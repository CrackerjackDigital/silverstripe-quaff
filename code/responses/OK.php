<?php
namespace Quaff\Responses;

use Quaff\Exceptions\Response as Exception;
use Quaff\Interfaces\Mapper;
use Quaff\Interfaces\Quaffable;
use Quaff\Interfaces\Reader;
use Quaff\Mappers\ArrayMapper;

abstract class OK extends Response {

	abstract public function read();

	public function getVersion() {
		return $this->meta('Version');
	}

	public function isOK() {
		return !$this->isError();
	}

	public function isError() {
		return false;
	}

	public function getItemCount() {
		return $this->meta('ItemCount');
	}

	public function getStartIndex() {
		return $this->meta('StartIndex');
	}

	public function getResultMessage() {
		return self::GenericOKMessage;
	}

	/**
	 * Return the items returned by the request as a list.
	 *
	 * @param int $options
	 * @return \SS_List
	 * @throws \Modular\Exceptions\Exception
	 * @throws \Quaff\Exceptions\Response
	 */
	public function getItems($options = Mapper::DefaultOptions) {
		$models = new \ArrayList();

		if ($this->isValid()) {
			$contentType = $this->getContentType();

			if (!$type = static::decode_content_type($contentType)) {
				throw new Exception("Bad content type '$contentType'");
			}
			$endpoint = $this->getEndpoint();

			while ($item = $this->read()) {
				/** @var Quaffable $model */
				if (!$model = $this->findModel($item, $options)) {

					if (!$model = $this->createEmptyModel($item, $options)) {

						$this->debug_error("Failed to locate model");
						continue;

					}
				}
				$model->quaff($endpoint, $item, $options);
				$models->push($model);
			}
		}
	}

	/**
	 * Return rawData as a json decoded value, generally an associative array.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function json() {
		if (is_null($json = json_decode($this->buffer, $this->get_config_setting('decode_options', self::ContentTypeJSON)))) {
			$message = "Failed to load json from response raw data";
			if ($error = json_last_error()) {
				$message .= ": '$error'";
			}
			throw new Exception($message);
		}
		if ($itemPath = $this->endpoint->getItemPath()) {
			$value = ArrayMapper::traverse($itemPath, $json, $found);

			if (!$found) {
				throw new Exception("Items node '$itemPath' not found in response");
			}
			return $value;
		} else {
			return $json;
		}
	}

	/**
	 * Return provided text as an xml DOM Document.
	 *
	 * @return \DOMDocument
	 * @throws Exception
	 */
	protected function xml() {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$doc = new \DOMDocument();
		if (!$doc->loadXML($this->buffer, $this->get_config_setting('decode_options', self::ContentTypeXML))) {

			$message = "Failed to load document from response raw data";
			if ($error = libxml_get_last_error()) {
				$message .= ": '$error'";
			}
			throw new Exception($message);
		}
		$xpath = new \DOMXPath($doc);
		if ($itemPath = $this->endpoint->getItemPath()) {
			return $xpath->query($itemPath);
		} else {
			return $xpath->query('/');
		}
	}

	/**
	 * Return provided text as an xml DOM Document.
	 *
	 * @return \DOMDocument
	 * @throws Exception
	 */
	protected function html() {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$doc = new \DOMDocument();
		if (!$doc->loadHTML($this->buffer, $this->get_config_setting('decode_options', self::ContentTypeHTML))) {
			$message = "Failed to load document from response raw data";

			if ($error = libxml_get_last_error()) {
				$message .= ": '$error'";
			}
			throw new Exception($message);
		}
		$xpath = new \DOMXPath($doc);
		if ($itemPath = $this->endpoint->getItemPath()) {
			return $xpath->query($itemPath);
		} else {
			return $xpath->query('/');
		}
	}

	/**
	 * Return an existing model from the provided item data or return null if not found. Override in implementation to
	 * find an existing model.
	 *
	 * @param array $data
	 * @param       $flags
	 * @return \DataObject|null
	 * @throws \Modular\Exceptions\NotImplemented
	 */
	protected function findModel($data, $flags = null) {
		return $this->getEndpoint()->findModel($data, $flags);
	}

	/**
	 * Call through to Endpoint, allow overload here. Returns a new model optionally initialised with passed data.
	 *
	 * @param array $data
	 * @param int   $flags
	 * @return \Quaff\Interfaces\Mapper
	 */
	protected function createEmptyModel($data, $flags = null) {
		return $this->getEndpoint()->createEmptyModel($data, $flags);
	}

	/**
	 * Return immediate data in first 'tier' of returned data without traversing a path, quicker than traverse.
	 *
	 * @param $key
	 * @return array
	 */
	public function data($key = null) {
		if (func_num_args()) {
			return array_key_exists($key, $this->buffer ?: []) ? $this->buffer[ $key ] : null;
		}
		return $this->buffer;
	}

	/**
	 * Return all the data from the buffer in one chunk.
	 *
	 * @return string
	 */
	public function getRawData() {
		$data = '';
		while ($buff = $this->read()) {
			$data .= $buff;
		}
		return $data;
	}


	/**
	 * Content types may have character encoding so just do a rude find of the expected content type in the response
	 * content type starting from the first character in lower-case.
	 *
	 * @param string|array $contentTypes we are looking to decode, could be an array if multiple content types were returned
	 * @return int|null the ContentTypeACB constant for the string content type or null if not found
	 */
	protected static function decode_content_type($contentTypes) {
		// convert to array if string so can handle the same
		$contentTypes = is_array($contentTypes) ? $contentTypes : [$contentTypes];

		$expectedTypes = static::config()->get('content_types');

		foreach ($contentTypes as $contentType) {
			foreach ($expectedTypes as $expectedType => $signatures) {
				foreach ($signatures as $signature) {
					if (0 === strpos(strtolower($contentType), strtolower($signature))) {
						return $expectedType;
					}
				}
			}
		}
		return null;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 *                      An offset to check for.
	 *                      </p>
	 * @return boolean true on success or false on failure.
	 *                      </p>
	 *                      <p>
	 *                      The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset) {
		return is_array($this->buffer) && array_key_exists($offset, $this->buffer);
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @return mixed
	 * @throws Exception
	 */
	public function offsetGet($offset) {
		if (!$this->offsetExists($offset)) {
			throw new Exception("Invalid key '$offset'");
		}
		return $this->buffer[ $offset ];
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		$this->buffer[ $offset ] = $value;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 *                      The offset to unset.
	 *                      </p>
	 * @return void
	 */
	public function offsetUnset($offset) {
		if ($this->offsetExists($offset)) {
			unset($this->buffer[ $offset ]);
		}
	}

}