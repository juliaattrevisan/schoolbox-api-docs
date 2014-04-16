<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;

class RevisionMapper extends Mapper {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('wikiRevision');
		$this->hasModel('\\Schoolbox\\Wiki\\Revision');

		$this->hasMap(array(
			'column'     => 'revisionID',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'wikiID',
			'property'   => 'wiki',
			'type'       => self::TYPE_MODEL,
			'join'       => 'wiki'
		));

		$this->hasMap(array(
			'column'     => 'heading',
			'property'   => 'heading',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'userID',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'join'       => 'user'
		));

		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'userID',
			'other_key' => 'id'
		));

		$this->hasJoin('wiki', array(
			'mapper'    => '\\Schoolbox\\Wiki\\WikiMapper',
			'this_key'  => 'wikiID',
			'other_key' => 'wikiID'
		));

	}

	/**
	 * @param int $categoryId
	 * @param int $limit
	 * @param int $offset
	 * @param int $time
	 * @return Collection|Revision[]
	 */
	public function findForFeed($categoryId, $limit, $offset = 0, $time = 0) {
		$time = date('Y-m-d H:i:s', $time ?: time());
		$limit = (int)$limit;
		$offset = (int)$offset;

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('r')},
				{$this->getJoinColumnString('user', 'u')},
				{$this->getJoinColumnString('wiki', 'w')},
				{$this->getJoinMapper('wiki')->getJoinColumnString('category', 'c')}
			FROM
				`{$this->getTable()}` `r`
				LEFT JOIN {$this->getJoinSql('user', 'r', 'u')}
				LEFT JOIN {$this->getJoinSql('wiki', 'r', 'w')}
				LEFT JOIN {$this->getJoinMapper('wiki')->getJoinSql('category', 'w', 'c')}
			WHERE
				`c`.`id` = :cat_id
				AND `w`.`deleted` = 'false'
				AND `r`.`created` < :time
			ORDER BY `r`.`created` DESC
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'cat_id' => $categoryId,
			'time'   => $time
		));

		$c = $this->createCollection();
		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			/** @var Revision $revision */
			$revision = $this->hydrate($r, 'r');
			$revision->setUser($this->hydrateJoin($r, 'user', 'u'));
			$revision->setWiki($this->hydrateJoin($r, 'wiki', 'w'));
			$revision->getWiki()->setCategory($this->getJoinMapper('wiki')->hydrateJoin($r, 'category', 'c'));
			$c->add($revision);
		}
		return $c;
	}


	/**
	 * Figure out if there are any empty links - these are assumed to be stubs.
	 * If a title attribute is provided, use that as the stub wiki heading
	 *
	 * @param string $body
	 * @return string
	 */
	public function createStubLinks($body) {
		preg_match_all('/<a title="([^"]*)">/', $body, $matches);

		if(!count($matches[1])) {
			return $body;
		}
		$pattern = array();
		$replace = array();

		foreach($matches[1] as $i => $match) {

			// Try using title attribute as a heading, otherwise ignore creation of empty stubs
			if(!strlen(trim($match))) {
				continue;
			}

			// Generate a slug for a new article that won't exist
			$url = new \Url(trim($match));
			$slug = $url->getSlug();

			$pattern[] = $matches[0][$i];
			$replace[] = "<a href='/wiki/{$body['categoryID']}/{$slug}'>";
		}

		// Perform replacements if required
		if (count($pattern)) {
			$body = str_replace($pattern, $replace, $body);
		}
		return $body;
	}

}
