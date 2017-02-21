<?php
namespace Quaff\Interfaces;

interface Response {

	public function getRawData();

	/**
	 * Return a response code, e.g. for HTTP this would be the Response Code
	 * @return mixed
	 */
	public function getResultCode();

	/**
	 * Return an error message if available.
	 * @return string
	 */
	public function getResultMessage();
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
	public function isError();

	/**
	 * Return true if the response has completed (e.g. no more data). An error response is always complete.
	 * @return bool
	 */
	public function isComplete();

	/**
	 * Return the opposite of isError
	 * @return boolean
	 */
	public function isOK();

	/**
	 * Check if a response is valid, that is it may not be an error, but it also might not be valid, such as containing correctly formed data or a wrong
	 * version to that expected.
	 *
	 * @return boolean
	 */
	public function isValid();

	/**
	 * Return a list of items returned from the request mapped to the local model.
	 * If a single item was returned for a single item request it will be the only item in the list.
	 *
	 * @return \SS_List
	 */
	public function getItems();

	/**
	 * Return the number of items returned (maybe one for a single model), 0 if none or null if not found.
	 *
	 * @return integer|null
	 */
	public function getItemCount();

	/**
	 * Return the start index from the api call if provided, e.g. for pagination, or null if not found.
	 *
	 * @return integer|null
	 */
	public function getStartIndex();

	/**
	 * Return returned version string or null if not found in response.
	 *
	 * @return string|null
	 */
	public function getVersion();

}