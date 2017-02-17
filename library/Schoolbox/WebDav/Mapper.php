<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;

class Mapper extends BaseMapper
{
    /**
     * Mapper constructor.
     */
    public function __construct()
    {
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setCampusMapper(CampusMapper::getInstance());

        parent::__construct();
    }

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('webdav');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('uri', self::TYPE_STRING);
        $this->uses('path', self::TYPE_STRING);
        $this->uses('username_prefix', self::TYPE_STRING);
        $this->uses('username_suffix', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->joins('campus_id', CampusMapper::class, 'id', null, null, 'campus', 'campus_id');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role_id');
        $this->hasSoftDeleteDateField('deleted_at');

    }

    /**
     * Here be the getters/setters
     */

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @param $roleMapper
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * @var CampusMapper
     */
    protected $campusMapper;

    /**
     * @param $campusMapper
     */
    public function setCampusMapper($campusMapper)
    {
        $this->campusMapper = $campusMapper;
    }

    /**
     * @return CampusMapper
     */
    public function getCampusMapper()
    {
        return $this->campusMapper;
    }
}
