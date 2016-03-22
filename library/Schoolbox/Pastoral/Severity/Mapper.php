<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Severity;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Pastoral\Severity\Model as SeverityModel;
use Schoolbox\Error\Mapper as ErrorMapper;

class Mapper extends BaseMapper
{
    public function __construct()
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        Parent::__construct();
    }

    protected function mapping()
    {
        $this->hasTable('luPastoralSeverity');
        $this->hasPrototype(new SeverityModel);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('order', self::TYPE_INTEGER);
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
