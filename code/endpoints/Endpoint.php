<?php

namespace Quaff\Endpoints;

use Modular\Debugger;

use Modular\Model;
use Modular\Object;
use Modular\tokens;
use Quaff\Exceptions\Endpoint as Exception;
use Quaff\Interfaces\Quaffable;
use Quaff\Responses\Response;
use Quaff\Transport\Transport;

use Quaff\Interfaces\Transport as TransportInterface;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Modular\Controller;
use Injector;

abstract class Endpoint extends Object implements EndpointInterface {
	use tokens;

	const FormatKeys   = 1;
	const FormatValues = 2;
	const FormatBoth   = 3;

	// override in concrete instance to give the class of the model returned by this endpoint
	const ModelClass = '';

	// override in concrete instance to give the class of the response returned by this endpoint
	const ResponseClass = '';

	const ErrorClass = 'Quaff\Responses\Error';

	/** @var  string the endpoint path e.g 'some-endpoint/get */
	protected $path;

	/** @var array endpoint information merged in constructor */
	protected $info = [
		#	'path' => 'someapiendpoint',        path relative to base url
		#   'base' => 'public'                  base path info
	];

	private static $default_params = [];

	public function __construct($path, array $info) {
		$this->path = $path;
		$this->info = $info;

		parent::__construct();
	}

	/**
	 * Perform any initialisation, e.g. authentication, clearing existing data etc.
	 */
	public function init() {
		$this->extend('quaffEndpointInit');
	}

	/**
	 * Quaff's the remote endpoint, pulling down all items and then writes them to local models.
	 */
	public function sync() {
		ob_start();

		$this->debugger(Debugger::DebugTrace)
			->toEmail('servers+fbu@moveforward.co.nz', Debugger::DebugNotice)
			->toFile('', Debugger::DebugTrace);

		$this->extend('startSync');
		$this->init();

		$models = new \ArrayList();

		$index = 0;

		/** @var \Quaff\Interfaces\Response $response */
		foreach ($this->quaff() as $response) {
 			if ($response->isValid()) {
				if ($items = $response->getItems()) {

					if ($count = $items->count()) {
						static::debug_trace("Adding '$count' items");

						/** @var Model $model */
						foreach ($items as $model) {
							try {
								$model->write();
								$models->push($model);

								static::debug_trace($index . ":" . var_dump($model->toMap()));

								$index++;

							} catch(Exception $e) {
								static::debug_message("Failed to add model: " . $e->getMessage(), Debugger::DebugWarn);
							}
						}
					} else {
						static::debug_trace("Finished after '$index' models");
						// no more items
						break;
					}
				}
			} else {
				$this->debug_error("Error response: " . $response->getResultMessage());
				break;
			}
		}
		$this->extend('endSync', $response, $models);
		ob_flush();
		return $models;
	}

	/**
	 * Call the remote endpoint and return an iterable set of responses with models available via getItems.
	 *
	 * @param array     $queryParams
	 * @param Quaffable $model to provide as a template when building the uri to call
	 * @return \Generator|\NoRewindIterator
	 * @api
	 */
	public function quaff(array $queryParams = [], $model = null) {
		/** @var TransportInterface $transport */
		$transport = Transport::factory(
			$this,
			$queryParams
		);

		do {
			$uri = $this->uri($queryParams, $model);

			/** @var Response $response */
			$response = $transport->get(
				$uri,
				$queryParams
			);
			yield $response;

		} while ($response->isValid());
	}

	/**
	 * Return full remote url with any query parameters from params and from model passed.
	 *
	 * @param array          $params additional query string parameters by reference so can do e.g. pager extensions
	 * @param Quaffable|null $model  which provided parameters to add to the url
	 * @return string
	 */
	protected function uri(array &$params = [], $model = null) {

		$url = Controller::join_links(
			$this->getBaseURL(),
			$this->getURL()
		);
		$queryParams = $this->queryParams($params, $model);
		$uriParams = $this->uriParams($params, $model);

		return $this->prepareURI($url, $uriParams, $queryParams);
	}

	/**
	 * Returns an array of query string segments in preference of config.params.get, model fields then params.
	 *
	 * @param array                $params by reference so can do e.g. pager extensions
	 * @param Quaffable|Model|null $model if not supplied getModelClass singleton will be used
	 * @return array
	 */
	protected function queryParams(array &$params = [], $model = null) {
		$params = array_merge(
			static::get_config_setting('default_params', $this->method()) ?: [],
			$params
		);

		$this->extend('updateQueryParameters', $params, $model);

		return $params;
	}

	/**
	 * Replaces tokens in the url which values from params. Override in concrete classes to provide custom url mangling.
	 *
	 * @param array $params
	 * @param null  $model
	 * @return array map of token name => value for parameters to add to the remote uri called, e.g. [ 'id' => 1212 ]
	 */
	public function uriParams(array &$params, $model = null) {
		$this->extend('updateURIParameters', $params, $model);
		return [];
	}

	/**
	 * Build a query string, we trust the parameters to have been properly encoded already.
	 *
	 * TODO handle arrays as values
	 *
	 * @param string $url
	 * @param array  $queryParams
	 * @return string
	 */
	protected function prepareURI($url, array $urlParams, array $queryParams) {
		$query = '';
		foreach ($queryParams as $name => $value) {
			$query .= "&$name=$value";
		}
		// merge provided params into the Endpoints default params, provided take preference
		$url = $this->detokenise(
			$url,
			$urlParams
		);

		return $url . '?' . substr($query, 1);
	}

	/**
	 * Attempts to find an existing model using the rules in the models quaff_map.
	 *
	 * @param      $apiData
	 * @param null $flags
	 * @return \Modular\Model|null
	 * @throws \Quaff\Exceptions\Exception
	 */
	public function findModel($apiData, $flags = null) {
		if (!$modelClass = $this->getModelClass()) {
			throw new Exception("No model class");
		}
		/** @var Model|\Quaff\Extensions\Model\Quaffable $temp */
		if ($temp = singleton($modelClass)) {
			// map api data into temp so can use for filters
			$temp->quaff($this, $apiData, Quaffable::MapOwnFieldsOnly);

			if ($map = $temp->quaffMapForEndpoint($this)) {
				/** @var \DataList $query */
				$query = $modelClass::get();

				foreach ($map as $modelPath => $info) {
					list($dataPath, $modelPath, $matches) = $info;
					if ($matches) {
						$query = $query->filter($modelPath, $temp->$modelPath);
					}
				}
				return $query->count() == 1 ? $query->first() : null;
			}
		}
	}

	/**
	 * Return a new instance of the model class returned from this endpoint.
	 * NB: no fields are set from apiData, you should call quaff on it to import the raw api data.
	 *
	 * @param array    $apiData optionally pass data to assist in model creation, doesn't get set on the model though
	 * @param int|null $flags
	 * @return \Modular\Model|null
	 */
	public function createEmptyModel($apiData = [], $flags = null) {
		if ($modelClass = $this->getModelClass()) {
			/** @var \Shuttlerock\Models\Model|Quaffable $model */
			if ($model = Injector::inst()->create($modelClass)) {
				return $model;
			}
		}
	}

	/**
	 * Overload in Endpoint implementation to return a suitable QuaffApiResponse derived object if not specified
	 * in info.response for the endpoint.
	 *
	 * @param $apiData - raw data from the api call result, e.g. array from json
	 * @return Response|null
	 */
	public function responseFactory($apiData) {
		if ($responseClass = $this->getResponseClass()) {
			return Injector::inst()->create($responseClass, $this, $apiData);
		}
		return null;
	}

	/**
	 * Returns the method suffix from the path, e.g. 'get' for 'some-endpoint/get'.
	 *
	 * @return string
	 */
	public function method() {
		$method = basename($this->getPath());
		return $method;
	}

	public static function match($pattern, $path) {
		$path = preg_replace('/{[^}]+}/', '*', $path);
		return fnmatch($pattern, $path);
	}

	public function getInfo() {
		return $this->info;
	}

	public function getModelClass() {
		return $this->info('model') ?: static::ModelClass;
	}

	public function getResponseClass() {
		return $this->info('response') ?: static::ResponseClass;
	}

	public function getErrorClass() {
		return $this->info('error') ?: static::ErrorClass;
	}

	/**
	 * @return string
	 */
	public function getEndpointClass() {
		return $this->info('endpoint');
	}

	/**
	 * @return string
	 */
	public function getTransportClass() {
		return $this->info('transport') ?: $this->config()->get('transport');
	}

	/**
	 * Returns the path component without the method, e.g. 'some-endpoint' for 'some-endpoint/get'
	 *
	 * @return string
	 */
	public function getURL() {
		return $this->info('url');
	}

	/**
	 * @return string
	 */
	public function getBaseURL() {
		return $this->info('base');
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->info('path') ?: $this->path;
	}

	/**
	 * @return string lowercase acceptType e.g. application/json
	 */
	public function getAcceptType() {
		return strtolower($this->info('accept_type') ?: $this->config()->get('accept_type'));
	}

	public function info($key) {
		return isset($this->info[ $key ]) ? $this->info[ $key ] : null;
	}
}