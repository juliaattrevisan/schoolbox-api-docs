<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Category;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Category\Model as PastoralCategoryModel;

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

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_category');
        $this->hasPrototype(new PastoralCategoryModel);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_type_id', self::TYPE_INTEGER);
        $this->uses('external_code', self::TYPE_STRING);
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
}
