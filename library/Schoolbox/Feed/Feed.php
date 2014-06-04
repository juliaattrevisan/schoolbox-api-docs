<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Feed;


use Schoolbox\Realtime\Notification\Decorator;

class Feed extends Decorator {

	/**
	 * Aggregates data from the given sources, returning it as a Collection of Feed\Items
	 *
	 * @param SourceInterface[] $sources
	 * @param int $context
	 * @param string $roleType
	 * @param int $limit
	 * @param int $offset
	 */
	public function aggregate($sources, $context, $roleType, $limit = SourceInterface::HARD_LIMIT, $offset = 0) {
		if ($limit <= 0 || $limit > SourceInterface::HARD_LIMIT) {
			$limit = SourceInterface::HARD_LIMIT;
		}

		foreach($sources as $channel) {
			if($channel instanceof SourceInterface) {

				/* Because each source can't know where its items fall chronologically, every source needs to
				 * retrieve $limit items, so that once combined it can reliably determine that it is returning
				 * the most up-to-date data. As such, there is an inherent (unavoidable?) performance hit, since
				 * the maximum number of items that might be retrieved can be up to count($sources) times the $limit
				 */
				$this->appendInstances($channel->getFeed($context, $roleType, $limit, $offset)->getAll());
			}
		}

		$this->sort();
	}

} 