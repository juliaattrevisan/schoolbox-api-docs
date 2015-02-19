<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Admin;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\RoleMapper;

class Controller extends BaseController
{
    /** @var RoleMapper */
    protected $roleMapper;

    /** @var NSMapper */
    protected $nsMapper;

    /** @var SourceMapper */
    protected $sourceMapper;


    /**
     * @return NSMapper
     */
    public function getNsMapper()
    {
        return $this->nsMapper ?: NSMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param NSMapper $nsMapper
     * @return self
     */
    public function setNsMapper($nsMapper)
    {
        $this->nsMapper = $nsMapper;
        return $this;
    }


    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper ?: RoleMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param RoleMapper $roleMapper
     * @return self
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }


    /**
     * @return SourceMapper
     */
    public function getSourceMapper()
    {
        return $this->sourceMapper ?: SourceMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param SourceMapper $sourceMapper
     * @return self
     */
    public function setSourceMapper($sourceMapper)
    {
        $this->sourceMapper = $sourceMapper;
        return $this;
    }


    /**
     * Shows the message configuration form
     *
     * @api   GET /adminv2/messages/configure
     * @level 150
     *
     * @param string $roletype
     */
    public function configure($roletype = null)
    {
        $perms = [
            0   => [ '&times;',  'Muted' ],
            200 => [ '&coloneq;', 'Digest' ],
            250 => [ '!',         'Instant' ],
            300 => [ '&rarr;',    'Push' ],
            400 => [ '&boxbox;',  'Email' ],
        ];

        $roletypes = ['all', 'student', 'staff', 'parent'];

        $filters = [
            'options'  => $roletypes,
            'selected' => array_search($roletype, $roletypes, true) ?: 0
        ];

        $r = $this->getRoleMapper();

        $rolec = $roletype ? $r->findByColumn('group_type', $roletype) : $r->findAll();
        $roles = $rolec->indexBy('id')->getAll();

        $ns = $this->getNsMapper()->findAll()->indexBy('id')->getAll();

        $sources = SourceMapper::getInstance()->findAll()->indexBy('id')->getAll();

        $roleIds = implode(',', $rolec->getColumnValues('id'));

        // TODO move this to a mapper
        $stmt = \Database::getInstance()->query("
            SELECT n.id namespace_id, s.id source_id, s.description source_description, r.id role_id, rp.min_level, rp.max_level, rp.default_level
            FROM msg_namespace n
            LEFT JOIN msg_source s ON n.id = s.namespace
            LEFT JOIN groups r ON r.group_type = s.role_type
            LEFT JOIN msg_route_perm rp ON rp.msg_source_id = s.id AND rp.role_id = r.id

            WHERE r.id IN ($roleIds)

            ORDER BY namespace_id, source_id, role_id
        ");

        $config = [];

        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
            list($nsId, $sId, $sKey, $rId, $min, $max, $def) = $r;

            $config[$nsId]['id'] = $nsId;
            $config[$nsId]['sources'][$sKey]['id'] = $sId;

            $enabled = !is_null($max);
            $mutable = $enabled && $min == 0;

            $config[$nsId]['sources'][$sKey]['roles'][$rId] = [
                'id'      => $rId,
                'enabled' => $enabled,
                'mutable' => $mutable,
                'default' => $def
            ];

        }


/*
        $config = [
            [
                'id' => 1,
                'sources' => [
                    [
                        'id' => 1,
                        'roles' => [
                            [
                                'id'      => 2,
                                'enabled' => true,
                                'mutable' => true,
                                'perms'   => [
                                    'x' => false,
                                    'd' => true,
                                    'i' => false,
                                    'p' => false,
                                    'e' => false,
                                ]
                            ],
                            [
                                'id'      => 4,
                                'enabled' => true,
                                'mutable' => false,
                                'perms'   => [
                                    'x' => false,
                                    'd' => false,
                                    'i' => true,
                                    'p' => true,
                                    'e' => true,
                                ]
                            ],
                            [
                                'id'      => 5,
                                'enabled' => false,
                                'mutable' => true,
                                'perms'   => [
                                    'x' => true,
                                    'd' => false,
                                    'i' => false,
                                    'p' => false,
                                    'e' => false,
                                ]
                            ],
                        ]
                    ]
                ]
            ]
        ];
*/

        $this->getResponse()->content->set([
            'title'    => 'Global Message Configuration',
            'template' => '@adminMessages/configure.twig',
            'data'     => [
                'filters' => $filters,
                'roles'   => $roles,
                'ns'      => $ns,
                'sources' => $sources,
                'perms'   => $perms,
                'config'  => $config,
            ]
        ]);
    }
} 