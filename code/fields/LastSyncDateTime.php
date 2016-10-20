<?php
namespace Quaff\Fields;

class LastSyncDateTime extends \Modular\Fields\DateTimeField {
	const SingleFieldName = 'QuaffLastSyncDateTime';
	const SingleFieldSchema = 'SS_DateTime';

	const DateRequired = false;
	const TimeRequired = false;

	public function afterQuaff() {
		$this()->{static::field_name()} = \SS_Datetime::now()->Rfc2822();
	}
}