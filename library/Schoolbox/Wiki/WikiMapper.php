<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Wiki;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Class WikiMapper
 *
 * @package Schoolbox\Wiki
 * @method Wiki createModel()
 */
class WikiMapper extends Mapper implements FeedableMapperInterface
{
    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('wiki');
        $this->hasModel(Wiki::class);
        $this->uses('wikiID', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('categoryID', FolderMapper::class, 'id', null, null, 'category', 'category');
        $this->joins('latestRevision', RevisionMapper::class, 'revisionID', null, null, 'latestRevision', 'revision');
        $this->uses('slug', self::TYPE_STRING);
        $this->uses('created', self::TYPE_DATETIME);
        $this->uses('deleted', self::TYPE_BOOL_ENUM, self::COLUMN_SOFTDELETE);
        $this->uses('locked', self::TYPE_BOOL_ENUM);
    }


    /**
     * @param int $categoryId
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|Wiki[]
     */
    public function findForFeed($categoryId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ?: time());
        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('w')},
				{$this->getJoinColumnString('category', 'c')},
				{$this->getJoinColumnString('revision', 'r')},
				{$this->getJoinMapper('revision')->getJoinColumnString('user', 'u')}
			FROM
				`{$this->getTable()}` `w`
				LEFT JOIN {$this->getJoinSql('category', 'w', 'c')}
				LEFT JOIN {$this->getJoinSql('revision', 'w', 'r', true)}
				LEFT JOIN {$this->getJoinMapper('revision')->getJoinSql('user', 'r', 'u', true)}
			WHERE
				`c`.`id` = :cat_id
				AND `w`.`created` < :time
				AND `w`.`deleted` = 'false'
			ORDER BY `w`.`created` DESC
			LIMIT $offset, $limit
		");

        $stmt->execute(array(
            'cat_id' => $categoryId,
            'time'   => $time
        ));

        $c = $this->createCollection();
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Wiki $wiki */
            $wiki = $this->hydrate($r, 'w');
            $wiki->setCategory($this->hydrateJoin($r, 'category', 'c'));
            $wiki->setLatestRevision($this->hydrateJoin($r, 'revision', 'r'));
            $wiki->getLatestRevision()->setUser($this->getJoinMapper('revision')->hydrateJoin($r, 'user', 'u'));
            $c->add($wiki);
        }
        return $c;
    }
}
