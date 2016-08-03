<?php
namespace Quaff;

use ClassInfo;
use Injector;
use Modular\Object;
use Quaff\Exceptions\Exception;
use Quaff\Interfaces\Endpoint;
use Quaff\Interfaces\Locator as LocatorInterface;
use Quaff\Interfaces\Mapper as MapperInterface;

abstract class Mapper extends Object
	implements MapperInterface, LocatorInterface
{
	// set this so all method's to call on model for value resolution  are prefixed by this,
	// e.g. 'quaff' for 'quaffURLSegment' if method is 'URLSegment'
	private static $map_method_prefix = self::DefaultMapMethodPrefix;

	private static $path_delimiter = self::DefaultPathDelimiter;

	private static $tag_delimiter = self::DefaultTagDelimiter;

	/** @var  Endpoint */
	protected $endpoint;

	private static $use_cache = true;

	public function __construct(Endpoint $endpoint) {
		$this->endpoint = $endpoint;
		parent::__construct();
	}

	/**
	 * Return an mapper which can handle the provided endpoint's data type (acceptType).
	 *
	 * @param Endpoint $endpoint
	 *
	 * @return MapperInterface
	 * @throws Exception
	 */
	public static function locate($endpoint) {
		$acceptType = $endpoint->getAcceptType();

		$mapper = static::cache($acceptType);

		if (!$mapper) {
			foreach (array_slice(ClassInfo::subclassesFor('Quaff\Mapper'), 1) as $className) {
				/** @var Mapper $mapper */
				$mapper = Injector::inst()->create($className, $endpoint);

				if ($mapper->match($acceptType)) {
					break;
				}

				$mapper = null;
			}
		}
		return static::cache($acceptType, $mapper);
	}

	/**
	 * Tests if the provided acceptType is in the array of acceptTypes.
	 *
	 * @param $acceptType
	 * @return bool
	 */
	public function match($acceptType) {
		return in_array($acceptType, $this->acceptTypes());
	}

	public function acceptTypes() {
		return $this->config()->get('accept_types') ?: [];
	}

	/**
	 * Convenience.
	 *
	 * @return string
	 */
	public static function path_delimiter() {
		return static::config()->get('path_delimiter');
	}

	/**
	 * Convenience.
	 *
	 * @return string
	 */
	public static function tag_delimiter() {
		return static::config()->get('tag_delimiter');
	}

}
