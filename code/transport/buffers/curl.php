<?php
namespace Quaff\Transport\Buffers;

trait curl {

	public function buffer($uri, array $options = []) {
		$curlHandle = curl_init($uri);

		curl_setopt_array(
			$curlHandle,
			$this->nativeOptions($options)
		);
		if (false !== ($result = curl_exec($curlHandle))) {
			$resultCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
			$result = curl_error($curlHandle);
			$contentType = curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE);
		} else {
			$resultCode = curl_errno($curlHandle);
			$result = curl_error($curlHandle);
			$contentType = 'text/plain';
		}
		return [
			$resultCode,
			$contentType,
			$result
		];
	}

}