<?php
use Modular\Object;

abstract class QuaffEndpoint extends Object
	implements QuaffEndpointInterface {
	const FormatKeys   = 1;
	const FormatValues = 2;
	const FormatBoth   = 3;

	// override in concrete instance to give the class of the model returned by this endpoint
	const ModelClass = '';

	// override in concrete instance to give the class of the response returned by this endpoint
	const ResponseClass = '';

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
	 * Call extensions beforeQuaff to perform any initialisation, e.g. authentication, clearing existing data etc.
	 */
	public function init() {
		$this->extend('beforeQuaff');
	}

	/**
	 * @param array                  $params
	 * @param QuaffMappableInterface $model
	 * @return array|SimpleXMLElement
	 * @api
	 */
	public function quaff(array $params = [], $model = null) {
		/** @var QuaffTransportInterface $transport */
		$transport = QuaffTransport::factory(
			$this,
			$params
		);

		/** @var QuaffApiResponse $response */
		$response = $transport->get(
			$this->uri($params, $model)
		);

		return $this->newResponse(
			$response
		);
	}

	/**
	 * Return full url including any query parameters
	 *
	 * @param array                       $params additional query string parameters
	 * @param QuaffMappableInterface|null $model
	 * @return string
	 */
	protected function uri(array $params = [], $model = null) {
		$url = $this->urlParams(
			Controller::join_links(
				$this->getBaseURL(),
				$this->getURL()
			),
			$params
		);

		$queryParams = $this->queryParams($params, $model);

		return self::build_query($url, $queryParams);
	}

	/**
	 * Returns an array of query string segments in preference of config.params.get, model fields then params.
	 *
	 * @param array                                  $params
	 * @param QuaffMappableInterface|DataObject|null $model if not supplied getModelClass singleton will be used
	 * @return array
	 */
	protected function queryParams(array $params = [], $model = null) {
		$fields = [];

		if (!$model) {
			$model = singleton($this->getModelClass());
		}

		if ($model) {
			// TODO handle recursive mapping for arrays/collections.
			// add model fields which are in the field map to the parameters as a name=value entry.
			$fields += array_filter(
				array_intersect_key(
					$model->toMap(),
					array_flip(
						array_keys(
							$model->quaffMapForEndpoint(
								$this,
								QuaffMappableInterface::MapOwnFieldsOnly
							)
						)
					)
				),
				function ($value) {
					return urlencode(trim($value));
				}
			);
		}
		$this->extend('updateQueryParameters', $params, $model);

		$query = array_merge(
			static::get_config_setting('default_params', $this->method()) ?: [],
			$fields,
			$params
		);

		return $query;
	}

	/**
	 * Build a query string, we trust the parameters to have been properly encoded already.
	 *
	 * TODO handle arrays as values
	 *
	 * @param       $url
	 * @param array $parameters
	 * @return string
	 */
	protected static function build_query($url, array $parameters) {
		$query = '';
		foreach ($parameters as $name => $value) {
			$query .= "&$name=$value";
		}
		return $url . '?' . substr($query, 1);
	}

	/**
	 * Return a new instance of the model class returned from this endpoint with optional data set.
	 * @param array $initData
	 * @return DataObject|null
	 */
	public function newModel(array $initData = null, $flags = null) {
		if ($modelClass = $this->getModelClass()) {
			return Injector::inst()->create($modelClass, $initData);
		}
		return null;
	}

	/**
	 * Overload in Endpoint implementation to return a suitable QuaffApiResponse derived object if not specified
	 * in info.response for the endpoint.
	 *
	 * @param $apiData - raw data from the api call result, e.g. array from json
	 * @return QuaffAPIResponse|null
	 */
	public function newResponse($apiData) {
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

	/**
	 * Override in concrete classes to provide versioning, token replacement etc.
	 *
	 * @param       $url
	 * @param array $params
	 * @return mixed
	 */
	public function urlParams($url, array $params = []) {
		return $url;
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