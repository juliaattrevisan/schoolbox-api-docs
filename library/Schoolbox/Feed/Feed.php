<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Feed;


use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Message\Messenger;

class Feed {


	/**
	 * @deprecated needs to be dependent on InstanceBundle, not Messenger
	 * @param Messenger $messenger
	 * @param int $timestamp
	 * @return Collection|Item[]
	 */
	public static function getItemsFromMessenger($messenger, $timestamp) {
		return $messenger->extractItems($timestamp);
	}

	/**
	 * @param IConfigLoader $conf
	 */
	function __construct($conf = null) {
		$this->conf = $conf instanceof IConfigLoader ? $conf : ConfigMapper::getInstance();
	}


	/**
	 * Aggregates data from the given sources, returning it as a Collection of Feed\Items
	 *
	 * @param SourceInterface[] $sources
	 * @param int $context
	 * @param string $roleType
	 * @param int $limit
	 * @param int $offset
	 * @return Collection|Item[]
	 */
	public function aggregate($sources, $context, $roleType, $limit = SourceInterface::SOFT_LIMIT, $offset = 0) {
		if ($limit <= 0 || $limit > SourceInterface::SOFT_LIMIT) {
			$limit = SourceInterface::SOFT_LIMIT;
		}

		$messages = new Collection();
		foreach($sources as $channel) {
			if($channel instanceof SourceInterface) {

				/* Because each source can't know where its items fall chronologically, every source needs to
				 * retrieve $limit items, so that once combined it can reliably determine that it is returning
				 * the most up-to-date data. As such, there is an inherent (unavoidable?) performance hit, since
				 * the maximum number of items that might be retrieved can be up to count($sources) times the $limit
				 */
				$messages->merge($channel->getFeed($context, $roleType, $limit, $offset));
			}
		}

		return $messages->sortBy('timestamp', 'desc')->slice($limit, $offset);
	}

} 