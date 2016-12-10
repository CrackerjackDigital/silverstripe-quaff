<?php

namespace Quaff\Endpoints;

use Injector;
use Modular\Debugger;
use Modular\Model;
use Modular\Object;
use Modular\tokens;
use Quaff\Exceptions\Endpoint as Exception;
use Quaff\Interfaces\Endpoint as EndpointInterface;
use Quaff\Interfaces\Locator as LocatorInterface;
use Quaff\Interfaces\Quaffable;
use Quaff\Interfaces\Transport as TransportInterface;
use Quaff\Transports\Protocol\http;
use Quaff\Transports\Transport;

class Endpoint extends Object implements EndpointInterface, LocatorInterface {
	use tokens;
	use http;

	const Version = '*';

	const FormatKeys   = 1;
	const FormatValues = 2;
	const FormatBoth   = 3;

	// override or set config in concrete instance to give the class of the model returned by this endpoint
	const ModelClass = '';
	private static $model;

	// override or set config in concrete instance to give the class of the response returned by this endpoint
	const ResponseClass = '';
	private static $response;

	// override or set config in concrete instance to give the class of the transport returned by this endpoint
	const TransportClass = '';
	private static $transport;

	// override or set config in concrete instance to give the class of the error returned by this endpoint
	const ErrorClass = 'Quaff\Responses\Error';
	private static $error;

	/** @var  string the endpoint alias e.g 'list:entries' set in constructor */
	private static $alias;

	/** @var array of aliases that this Endpoint uses to fill out it's own meta */
	private static $parents = [
		#   'directory:assets',
		#   'filetype:csv'
	];

	/** @var array endpoint information merged in constructor */
	private static $meta = [
		#	'path' => '/api/{version}/',        path relative to base url
		#   'base' => 'public'                  base path info
		#   'accept_type' => 'application/json'
	];
	// these meta variables will taken from parent meta values and concatenated to build up a full path, see buildMetaData
	private static $meta_append = [
		'url'  => '/',
		'path' => '/',
	];

	private static $default_params = [];

	private static $version = '';

	//set by ctor as merge of config.config and passed meta parameter
	protected $metaData;

	public function __construct(array $moreMeta = []) {
		$this->metaData = $this->buildMetaData($moreMeta);
		parent::__construct();
	}

	/**
	 * Quaff's the remote endpoint, pulling down all items and then writes them to local models.
	 */
	public function sync() {
		ob_start();

		$this->debugger()
			->toFile($this->getAlias())
			->sendFile();

		$this->extend('startSync');
		$this->init();

		$written = new \ArrayList();

		$index = 0;

		/** @var \Quaff\Interfaces\Response $response */
		foreach ($this->quaff() as $response) {
			if ($response->isValid()) {
				if ($items = $response->getItems()) {

					if ($count = $items->count()) {
						$this->debug_info("Adding '$count' items");

						/** @var Model $model */
						foreach ($items as $model) {
							try {
								$model->write();
								$written->push($model);

								$this->debug_trace($index . ":" . var_dump($model->toMap()));

								$index++;

							} catch (Exception $e) {
								$this->debug_error("Failed to add model: " . $e->getMessage());
							}
						}
					} else {
						static::debug_info("Finished after '$index' models");
						// no more items
						break;
					}
				}
			} else {
				$this->debug_error("Error syncing model response: " . $response->getResultMessage());
				break;
			}
			if ($response->isComplete()) {
				// no more to come.
				break;
			}
			ob_flush();
		}
		$this->extend('endSync', $response, $written);
		ob_flush();

		return $written;
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
			/** @var \Quaff\Responses\Response $response */
			$response = $transport->get(
				$this->getURI(Transport::ActionRead),
				$queryParams
			);
			yield $response;

		} while ($response->isValid() && !$response->isComplete());
	}

	/**
	 * Most meta is treated as normal for silverstripe config, however some values such as url and path are instead appended with those from the
	 * Endpoint aliases listed as 'parents'. So if an Endpoint has a path of 'assets' and it has a parent listed with a path of '/var/www/html' then
	 * the path of the endpoint will be '/var/www/html/assets'. If more than one parent is listed then paths are appended so first parent is first, then
	 * second parent is appended, then finally this endpoints path.
	 *
	 * @param array $moreMeta
	 * @return mixed
	 */
	public function buildMetaData(array $moreMeta = []) {
		$parents = $this->config()->get('parents') ?: [];
		$appendMeta = $this->config()->get('meta_append') ?: [];

		$built = [];
		foreach ($parents as $parentAlias) {
			/** @var Endpoint $parentEndpoint */
			if ($parentEndpoint = static::locate($parentAlias)) {
				foreach ($appendMeta as $name => $separator) {
					if ($value = $parentEndpoint->meta($name)) {
						$built[ $name ] = $value . $separator;
					}
				}
			}
		}

		// now we tack on our own append values which are not merged via 'normal' config mechanism
		$meta = $this->config()->get('meta');
		foreach ($appendMeta as $name => $separator) {
			if (isset($meta[ $name ])) {
				$built[ $name ] = (isset($built[ $name ]) ? $built[ $name ] : '') . $meta[ $name ];
			}
		}
		// now get the values which are not 'append' values and which have been merged 'normally' by config so we can merge in to completed metaData
		$noAppended = array_diff_key(
			$this->config()->get('meta'),
			$appendMeta
		);

		return array_merge_recursive(
			array_filter($built),
			$moreMeta,
			$noAppended
		);
	}

	/**
	 * Find an object based on the specs, optionally caching it for later re-retrieval.
	 *
	 * @param  string $alias whatever is needed by the locator to find the target.
	 * @param array   $moreMeta
	 * @return \Quaff\Interfaces\Endpoint yields instance of class being called which matches test criteria
	 */
	public static function locate($alias, array $moreMeta = []) {
		foreach (Endpoint::subclasses() as $namespaced => $className) {
			if (\Config::inst()->get($namespaced, 'alias') == $alias) {
				return new $namespaced($moreMeta);
			}
		}
		return null;
	}

	/**
	 * Test alias is the same as this endpoints alias
	 *
	 * @param string $alias to match
	 * @return bool
	 */
	public function match($alias) {
		$thisAlias = static::config()->get('alias', \Config::UNINHERITED);
		return $thisAlias == $alias;
	}

	/**
	 * Perform any initialisation, e.g. authentication, clearing existing data etc.
	 */
	public function init() {
		$this->extend('quaffEndpointInit');
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
			/** @var Model|Quaffable $model */
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

	public function getMetaData() {
		return $this->metaData;
	}

	public function auth() {
		return true;
	}

	/**
	 * Returns the method suffix from the alias, e.g. 'list' for 'list:entries'.
	 *
	 * @return string
	 */
	public function method() {
		$method = current(explode(':', $this->getAlias()));
		return $method;
	}

	public function version() {
		return $this->config()->get('version') ?: static::Version;
	}

	public function getAlias() {
		return $this->config()->get('alias');
	}

	public function getResponseClass() {
		return $this->config()->get('response') ?: static::ResponseClass;
	}

	public function getTransportClass() {
		return $this->config()->get('transport') ?: static::TransportClass;
	}

	public function getModelClass() {
		return $this->config()->get('model') ?: static::ModelClass;
	}

	public function getErrorClass() {
		return $this->config()->get('error') ?: static::ErrorClass;
	}

	/**
	 * @return string
	 */
	public function getEndpointClass() {
		return get_called_class();
	}

	/**
	 * Return all meta data or for a specific key if supplied.
	 *
	 * @param string|null $key
	 * @return null
	 */
	public function meta($key = null) {
		if (func_num_args() > 0) {
			return isset($this->metaData[ $key ]) ? $this->metaData[ $key ] : null;
		} else {
			return $this->metaData;
		}
	}

	/**
	 * Return a full uri for url and path for the action. Uses prepareURI provided by an extension
	 * such as http.
	 *
	 * @param string $action
	 * @return string
	 */
	public function getURI($action) {
		return $this->prepareURI(
			\Controller::join_links($this->getURL(), $this->getPath()),
			$action
		);
	}

	/**
	 * Returns the url of source
	 *
	 * @return string
	 */
	public function getURL() {
		return $this->meta('url');
	}

	/**
	 * Return the path which is appended to the url for this endpoint, url may be from a parent
	 * but path is specifically for this resource.
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->meta('path');
	}

	/**
	 * Return path to items collection in the overall response data.
	 *
	 * e.g. 'items' for the items property in the following json response:
	 *  {
	 *      count: 100,
	 *      items: [
	 *              { id: 1, ... }
	 *      ]
	 *  }
	 *
	 * @return string
	 */
	public function getItemPath() {
		return $this->meta('item_path');
	}

	/**
	 * @return string lowercase acceptType e.g. application/json
	 */
	public function getAcceptType() {
		return strtolower($this->meta('accept_type'));
	}

}