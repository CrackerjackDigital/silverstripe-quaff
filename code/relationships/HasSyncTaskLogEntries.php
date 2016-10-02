<?php
namespace Quaff\Relationships;

use Modular\Relationships\HasMany;

class HasSyncLogEntries extends HasMany {
	const RelationshipName = 'SyncLogEntries';
	const RelatedClassName = 'Quaff\Models\SyncLogEntry';

	private static $show_as = self::ShowAsGridField;
	private static $allow_add_new = false;
}   