<?php
namespace Quaff\Controllers;

use Modular\Controller;
use Quaff\Api;
use SS_HTTPRequest;

class Model extends Controller {
	const ModelClass = '';

	private static $allowed_actions = [
		'all' => '->canView',
		'one' => '->canView',
	];

	public static function model_class() {
		return static::ModelClass;
	}

	/**
	 * Add url handlers for the controlled model.
	 *
	 * @return array
	 */
	public function quaffStatics() {
		$api = Api::locate($this->config()->get('service'));
		$listEndpoint = $api->endpointForModel(static::model_class(), 'list');
		$itemEndpoint = $api->endpointForModel(static::model_class(), 'item');
		return [
			'url_handlers' => [
				$listEndpoint->info('url_handler')           => 'all',
				$itemEndpoint->info('url_handler') . '/$ID!' => 'one',
			],
		];
	}

	public function all(SS_HTTPRequest $request) {

	}

	public function one(SS_HTTPRequest $request) {

	}

}