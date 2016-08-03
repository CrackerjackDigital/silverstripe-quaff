<?php
namespace Quaff;

class ModelExtension extends \Modular\ModelExtension {
	private static $db = [
		'QuaffLastSyncDateTime' => 'SS_DateTime'
	];
}