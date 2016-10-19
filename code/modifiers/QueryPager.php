<?php
namespace Quaff\Modifiers;

use Modular\ModelExtension;

/**
 * Adds a pager to the outgoing api query.
 *
 * @package Quaff\Transforms
 */
class QueryPager extends ModelExtension {

	public function updateQueryParameters(array &$params) {
		list($startPage, $pageLength, $pageVar, $lengthVar) = $this->pagination();

		if ($pageVar && !is_null($startPage)) {
			if (array_key_exists($pageVar, $params)) {
				// if already there then increment it as this is not the first time around
				$params[$pageVar] = $params[$pageVar] + 1;
			} else {
				$params[$pageVar] = $startPage;
			}
		}
		if ($lengthVar && !is_null($pageLength)) {
			$params[$lengthVar] = $pageLength;
		}
	}

	protected function pagination() {
		$pagination = array_merge(
			[
				'start' => null,
				'length' => null,
				'page_var' => null,
				'length_var' => null
			],
			$this()->info('pagination')
		);
		return array_values([
			'start' => $pagination['start'],
		    'length' => $pagination['length'],
		    'page_var' => $pagination['page_var'],
			'length_var' => $pagination['length_var']
		]);
	}
}