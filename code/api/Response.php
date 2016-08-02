<?php

abstract class QuaffAPIResponse extends Object {
	const SimpleMatchKey = 'request';

	const RawDataArray = 'array';

	protected $data = array();

	/** @var QuaffEndpointInterface */
	protected $endpoint = null;

	private static $status_ok = 'OK';

	public function __construct(QuaffEndpointInterface $endpoint, array $data = array()) {
		$this->endpoint = $endpoint;
		$this->data = $data;
		parent::__construct();
	}

	public function __get($name) {
		return $this->hasMethod("get$name") ? $this->{"get$name"}() : $this->data($name);
	}

	/**
	 * Return returned version string or null if not found in response.
	 *
	 * @return string|null
	 */
	abstract public function getResponseVersion();

	/**
	 * Check if the request was invalid, e.g. bad/missing parameters but we got something back from API.
	 *
	 * For HTTP errors an exception will be thrown when the request is made instead as maybe config or remote endpoint
	 * wrong or not available at the moment but probably not recoverable.
	 *
	 * If this returns true then getNativeCode should return the API error result code if there is one,
	 * and getMessage should return the error message if there is one.
	 *
	 * @return boolean
	 *      true if request failed (bad url, invalid parameters passed etc)
	 *      false if something returned (maybe empty though)
	 */
	abstract public function isError();

	/**
	 * Return the number of items returned (maybe one for a single model), 0 if none or null if not found.
	 *
	 * @return integer|null
	 */
	abstract public function getItemCount();

	/**
	 * Return the start index from he api call if provided, e.g. for pagination, or null if not found.
	 *
	 * @return integer|null
	 */
	abstract public function getStartIndex();

	/**
	 * Return the items returned by the request as a list.
	 *
	 * @param int $options
	 * @return SS_List
	 */
	public function getItems($options = QuaffMapper::DefaultOptions) {
		return $this->items($this->data(), $options);
	}

	/**
	 * Return list of Models populated from the provided list of raw items.
	 *
	 * Items are either existing found using 'findModel' or new models via 'makeModel'
	 * updated from the item data via their 'quaff' method.
	 *
	 * @param array|\Traversable $items
	 * @param                    $flags
	 * @return \ArrayList
	 */
	protected function items($items, $flags = null) {
		$models = new ArrayList();

		if ($this->isValid()) {

			$endpoint = $this->getEndpoint();

			foreach ($items as $item) {
				/** QuaffModelInterface */
				if (!$model = $this->findModel($item, $flags)) {
					$model = $endpoint->newModel($item, $flags);
				}
				// call this directly instead of extend.
				$model->quaff($endpoint, $item, $flags);

				$models->push($model);
			}

		}
		return $models;
	}

	/**
	 * Call through to Endpoint, allow overload here. Returns a new model optionally initialised with passed data.
	 *
	 * @param array $data
	 * @param int   $flags
	 * @return QuaffMappableInterface
	 */
	protected function newModel(array $data = null, $flags = null) {
		return $this->getEndpoint()->newModel($data);
	}

	/**
	 * Return an existing model from the provided item data or return null if not found. Override in implementation to
	 * find an existing model. By default returns null.
	 *
	 * @param array $data
	 * @param       $flags
	 * @return DataObject|QuaffModelInterface|null
	 */
	protected function findModel(array $data, $flags) {
		return null;
	}

	/**
	 * @return QuaffEndpointInterface|Object
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}

	/**
	 * Return immediate data in first 'tier' of returned data without traversing a path, quicker than traverse.
	 *
	 * @param $key
	 * @return array
	 */
	public function data($key = null) {
		return $key
			? isset($this->data[ $key ]) ? $this->data[ $key ] : null
			: $this->data;
	}

	/**
	 * Return the status code, e.g. 200 or 500
	 *
	 * @return int
	 */
	public function getResponseCode() {
		return $this->data('Code');
	}

	/**
	 * Return a useful translated message, e.g. 'ok' or error text.
	 *
	 * @return string|null
	 */
	public function getResultMessage() {
		return $this->data('Message');
	}

	public function getURI() {
		return $this->data('URI');
	}

	/**
	 * Call from inherited classes for basic validity checks before specific ones.
	 * Initially just returns the opposite of isError.
	 *
	 * @return bool
	 */
	public function isValid() {
		return !$this->isError();
	}

	/**
	 * Returns the raw data from the response.
	 *
	 * @return array
	 * @internal param string $format - does nothing at the moment, always returns an array.
	 */
	public function getRawData() {
		return $this->data;
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
		return is_array($this->data) && array_key_exists($offset, $this->data);
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @return mixed
	 * @throws QuaffException
	 */
	public function offsetGet($offset) {
		if (!$this->offsetExists($offset)) {
			throw new QuaffException("Invalid key '$offset'");
		}
		return $this->data[ $offset ];
	}

	/**
	 * ArrayAccess implementation
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		$this->data[ $offset ] = $value;
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
			unset($this->data[ $offset ]);
		}
	}

}
