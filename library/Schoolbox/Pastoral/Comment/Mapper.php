<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Comment;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;

class Mapper extends BaseMapper
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Mapper constructor.
     */
    public function __construct()
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        Parent::__construct();
    }

    protected function mapping()
    {
        $this->hasTable('pastoral_comment');
        $this->hasPrototype(new PastoralCommentModel);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('datetime_posted', self::TYPE_DATETIME);
        $this->uses('datetime_modified', self::TYPE_DATETIME);
        $this->uses('datetime_deleted', self::TYPE_DATETIME);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner');
        $this->hasSoftDeleteDateField('datetime_deleted');
    }

    /**
     * Retrieves all access relating to a particular pastoral id
     *
     * @param  int $pastoralId
     *
     * @return \Schoolbox\Collection
     */
    public function findByPastoralId($pastoralId) {
        $collection = new Collection();
        $userMapper = UserMapper::getInstance();

        $query = "
			SELECT
				{$this->getColumnString('a')},
				{$this->getJoinColumnString('owner', 'u')}
			FROM
				`{$this->getTable()}` `a`
				LEFT JOIN {$this->getJoinSql('owner', 'a', 'u')}
			WHERE
				`a`.`datetime_deleted` IS NULL AND
				`a`.`pastoral_id` = :pastoralId
			";


        $statement = $this->database->prepare($query);
        $statement->bindParam(':pastoralId', $pastoralId);
        $statement->execute();

        foreach ($statement as $row) {
            $pastoral = $this->hydrate($row, 'a');
            $owner = $userMapper->hydrate($row, 'u');

            $pastoral->setOwner($owner);
            $collection->add($pastoral);
        }

        return $collection;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper() {
        return $this->errorMapper;
    }
}