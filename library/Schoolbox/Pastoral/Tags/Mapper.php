<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Tags;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Tags\Model as PastoralTagsModel;

class Mapper extends BaseMapper implements BaseMapper\UsableAsTagSource
{
    /**
     * Mapper constructor.
     */
    public function __construct()
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        Parent::__construct();
    }

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_tags');
        $this->hasModel(PastoralTagsModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

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

    /**
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @return Collection|PastoralTagsModel[]
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT)
    {
        if (strlen($keyword) === 0) {
            return new Collection();
        }
        $query = "
            {$this->getSelectString()}
            WHERE name LIKE :search
            LIMIT :limit OFFSET :offset
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', $this->getDatabase()->quoteForLike($keyword).'%', \PDO::PARAM_STR);
        $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }
}
