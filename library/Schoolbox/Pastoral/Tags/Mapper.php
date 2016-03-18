<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Tags;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Tags\Model as PastoralTagsModel;

class Mapper extends BaseMapper
{
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
        $this->hasTable('pastoral_tags');
        $this->hasPrototype(new PastoralTagsModel);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('datetime_deleted', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('datetime_deleted');
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
}
