<?php
namespace Quaff\Transport\Copiers;

trait stream {
	public function copy($fpFrom, $fpTo, &$contentLength = null) {
		$contentLength = stream_copy_to_stream($fpFrom, $fpTo);
		return $this;
	}
}