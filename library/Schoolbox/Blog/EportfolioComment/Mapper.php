<?php
/**
 * Mapper for e-portfolio blog comments
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\EportfolioComment;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Blog\EportfolioComment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\EportfolioBlog\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\EportfolioBlog\Model as EportfolioBlogModel;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Feed\FeedableMapperInterface;

class Mapper extends BaseMapper implements FeedableMapperInterface
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('eportfolio_component_blog_comment');
        $this->hasPrototype(new EportfolioBlogCommentModel);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('blog_id', EportfolioBlogMapper::class, 'id', null, null, 'blog');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->hasSoftDeleteDateField('deleted_at');

        $this->hasJoin(
            'owner',
            array(
                'mapper' => '\\Schoolbox\\UserMapper',
                'this_key' => 'owner_id',
                'other_key' => 'id'
            )
        );

        $this->hasJoin(
            'blog',
            array(
                'mapper' => '\\Schoolbox\\Blog\\EportfolioBlog\\Mapper',
                'this_key' => 'blog',
                'other_key' => 'id'
            )
        );
    }

    /**
     * Returns a list of years and months which have blog posts
     *
     * @param int $ownerId
     * @return array
     */
    public function getOccupiedDateList($ownerId)
    {
        $stmt = $this->database->prepare(
            "
			SELECT YEAR(`created_at`) as 'year', `created_at`, MONTH(`created_at`) as 'month'
			FROM {$this->getTable()}
			WHERE `owner_id` = :owner_id
			GROUP BY `year` DESC, `month` DESC
		"
        );

        $stmt->execute(array('owner_id' => $ownerId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param int $ownerId
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|EportfolioBlogModel[]
     */
    public function findForFeed($folderId, $limit, $offset = 0, $time = 0)
    {
        $time = date('Y-m-d H:i:s', $time ? : time());

        $limit = (int)$limit;
        $offset = (int)$offset;

        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getColumnString('bc')},
				{$this->getJoinColumnString('owner', 'u')},
				{$this->getJoinColumnString('blog', 'b')}
			FROM `{$this->getTable()}` AS `bc`
				LEFT JOIN {$this->getJoinSql('owner', 'bc', 'u')}
				INNER JOIN {$this->getJoinSql('blog', 'bc', 'b')}
			WHERE `bc`.`deleted_at` IS NULL
				AND `b`.`folder_id` = :folder_id
				AND `bc`.`created_at` < :time
			ORDER BY `bc`.`created_at` DESC
			LIMIT $offset, $limit
		"
        );

        $stmt->execute(
            array(
                'folder_id' => $folderId,
                'time' => $time
            )
        );

        $c = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $owner */
            $owner = $this->hydrateJoin($r, 'owner', 'u');

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($r, 'folder', 'c');

            /** @var EportfolioBlogModel $blog */
            $blog = $this->hydrate($r, 'b');
            $blog->setOwner($owner);
            $blog->setFolder($folder);
            $c->add($blog);
        }

        return $c;
    }

    /**
     * Find all the comments for a particular blog
     *
     * @param EportfolioBlogModel $blog
     * @return Collection
     */
    public function findForBlog(EportfolioBlogModel $blog) {
        $stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('c')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `c`
            LEFT JOIN {$this->getJoinSql('owner', 'c', 'u')}
			WHERE
				`c`.`deleted_at` IS NULL
				AND `c`.blog_id = :blogId
			ORDER BY `c`.`created_at` DESC
		");

        $stmt->execute(array(
            'blogId' => $blog->getId()
        ));

        $c = $this->createCollection();

        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'owner', 'u');

            /** @var EportfolioBlogCommentModel $comment */
            $comment = $this->hydrate($r, 'c');
            $comment->setOwner($user);
            $c->add($comment);
        }

        return $c;
    }
}