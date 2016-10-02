<?php
namespace Quaff\Fields;

use Modular\Fields\Field;

class SyncOutcome extends Field {
	const SingleFieldName = 'SyncOutcome';
	const SingleFieldSchema = 'Varchar(32)';

	const OutcomeOK = 'OK';
	const OutcomeFailed = 'Failed';
}