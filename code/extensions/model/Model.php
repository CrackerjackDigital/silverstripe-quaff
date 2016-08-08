<?php
namespace Quaff\Extensions\Model;

use Modular\ModelExtension;

class Model extends ModelExtension {
	private static $db = [
		'QuaffLastSyncDateTime' => 'SS_DateTime'
	];
}