<?php
namespace Quaff\Interfaces;

interface Transport {
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

	const MetaContentType   = 'ContentType';
	const MetaException     = 'Exception';
	const MetaResultMessage = 'ResultMessage';

	/**
	 * @param string $uri
	 * @param array  $options to pass to underlying transport mechanism, e.g. guzzle or curl or php context
	 * @return Response
	 */
	public function get($uri, array $options = []);

	/**
	 * Check if a resource, file etc exists, connection can be made etc. e.g. HTTP may do a HEAD request instead of getting the full document.
	 *
	 * @param       $uri
	 * @param array $options
	 * @return mixed
	 */
	public function exists($uri, array $options = []);
}