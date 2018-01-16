<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Mggs\Learning\Personalisad\Admin;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;


class Controller extends Base
{
    public const CONFIG = 'pbl_roles_enabled';

    /**
     * @var RoleMapper
     */
    private $roleMapper;
    /**
     * @var ConfigMapper
     */
    private $configMapper;


    /**
     * @param RoleMapper   $roleMapper
     * @param ConfigMapper $configMapper
     */
    public function __construct(RoleMapper $roleMapper, ConfigMapper $configMapper)
    {
        $this->roleMapper = $roleMapper;
        $this->configMapper = $configMapper;
    }


    /**
     * @api GET /adminv2/pbl
     */
    public function getIndex()
    {
        $data = [
            'roles'   => $this->roleMapper->findByColumn('group_type', Role::STUDENT),
            'enabled' => explode(',', $this->getConfigLoader()->get(self::CONFIG))
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@mggsAdminPersonalised/index.twig', $data));
    }


    /**
     * @api POST /adminv2/pbl
     */
    public function postRoleInvolvement()
    {
        $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('mggsAdminPersonalised.getIndex'));

        $conf     = $this->configMapper->findOneByColumn('name', self::CONFIG);
        $involved = implode(',', $this->getRequest()->post->get('role') ?? []);

        $conf->setValue($involved);

        $this->configMapper->save($conf);
    }
}