<?php
namespace Quaff\Interfaces;

interface Transport extends Reader, Buffer, Protocol, Decoder {
	const ResponseDecodeOK    = 'OK';
	const ResponseDecodeError = 'Error';

	// generic 'ok' response, error responses should be more informative
	const ResultMessageOK = 'OK';

	const ActionRead     = 1;
	const ActionExists   = 3;         // 2 + 1 can't exists without read
	const ActionWrite    = 4;
	const ActionCreate   = 9;         // 8 + 1 can't create without write
	const ActionTruncate = 17;        // 16 + 1 can't truncate without write
	const ActionDelete   = 36;        // 32 + 4 can't delete without write
	const ActionDecode   = 64;        // used for native options to do with decoding data
	const ActionEncode   = 128;

	const MetaContentType   = 'Content-Type';
	const MetaContentLength = 'Content-Length';
	const MetaException     = 'Exception';
	const MetaResultMessage = 'ResultMessage';
	const MetaResponseCode  = 'ResponseCode';

	/**
	 * @param string $uri
	 * @param array  $queryParams to pass to underlying transport mechanism, e.g. guzzle or curl or php context
	 * @return Response
	 */
	public function get($uri, array $queryParams = []);

}