<?php
use Modular\ModelExtension;

class QuaffModelExtension extends ModelExtension {
	private static $db = [
		'QuaffLastSyncDateTime' => 'SS_DateTime'
	];
}