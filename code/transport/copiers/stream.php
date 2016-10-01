<?php
namespace Quaff\Transport\Copiers;

trait stream {
	public function copy($fpFrom, $fpTo) {
		return stream_copy_to_stream($fpFrom, $fpTo);
	}
}