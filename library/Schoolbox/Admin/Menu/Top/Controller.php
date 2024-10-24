<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Menu\Top;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Menu\Icon\Mapper as MenuIconMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Top\Campus\Mapper as MenuTopCampusMapper;
use Schoolbox\Menu\Top\Role\Mapper as MenuTopRoleMapper;
use Schoolbox\Menu\Top\Campus\Model as MenuTopCampus;
use Schoolbox\Menu\Top\Role\Model as MenuTopRole;
use Schoolbox\Menu\Top\Mapper as MenuTopMapper;
use Schoolbox\Menu\Top\Model as MenuTop;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Menu\Top
 */
class Controller extends Base
{
    const PAGE_CURRENT = 'menu-top';

    /** @var MenuIconMapper $menuIconMapper */
    private $menuIconMapper;

    /** @var MenuTopMapper $menuTopMapper */
    private $menuTopMapper;

    /** @var MenuTopCampusMapper $menuTopCampusMapper */
    private $menuTopCampusMapper;

    /** @var MenuTopRoleMapper $menuTopRoleMapper */
    private $menuTopRoleMapper;

    /** @var RoleMapper $roleMapper */
    private $roleMapper;

    /** @var CampusMapper $campusMapper */
    private $campusMapper;

    public function __construct()
    {
        $this->setMenuIconMapper(MenuIconMapper::getInstance());
        $this->setMenuTopMapper(MenuTopMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setCampusMapper(CampusMapper::getInstance());
        $this->setMenuTopCampusMapper(MenuTopCampusMapper::getInstance());
        $this->setMenuTopRoleMapper(MenuTopRoleMapper::getInstance());
    }

    /**
     * Display re-order screen
     *
     * @api   GET /adminv2/menu-top
     * @since 165
     */
    public function getIndex()
    {
        $this->response = $this->responseFromTemplate(
            '@adminMenuTop/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminMenuTop.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminMenuTop.getIndex')),
                'menu' => $this->getMenuTopMapper()->findByColumn('plan_hide', 0, ['order' => ['sort_order' => BaseMapper::SORT_ORDER_ASC]])
            ],
            'Admin - Top Menu'
        );
    }

    /**
     * Display modification screen
     *
     * @api   GET /adminv2/menu-top/modify/{id}
     * @since 165
     *
     * @param int $id
     */
    public function getModify($id)
    {
        // Get selected campuses and roles

        $selections = $this->getMenuTopRoleMapper()->findByColumn('menu_top_id', $id);

        $rolesSelected = [];

        /** @var MenuTopRole $selection */
        foreach ($selections as $selection) {
            $rolesSelected[$selection->getRoleId()] = true;
        }

        $selections = $this->getMenuTopCampusMapper()->findByColumn('menu_top_id', $id);

        $campusesSelected = [];

        /** @var MenuTopCampus $selection */
        foreach ($selections as $selection) {
            $campusesSelected[$selection->getCampusId()] = true;
        }

        $this->response = $this->responseFromTemplate(
            '@adminMenuTop/modify.twig',
            [
                'resource' => $this->getDi()->get('sitePage')->getLocalisedTerm('resource', true, true),
                'pageCurrent' => $this->routeGenerator->generate('adminMenuTop.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminMenuTop.getIndex')),
                    [['Modify', null]]
                ),
                'item' => $this->getMenuTopMapper()->findOneByColumn($this->getMenuTopMapper()->getIdentifierColumn(), $id, ['cache' => false]),
                'icons' => $this->getMenuIconMapper()->findAll(['order' => ['title' => BaseMapper::SORT_ORDER_ASC]]),
                'roles' => $this->getRoleMapper()->findAll(['order' => ['sequence' => BaseMapper::SORT_ORDER_ASC]]),
                'rolesSelected' => $rolesSelected,
                'campuses' => $this->getCampusMapper()->findAll(['order' => ['sequence' => BaseMapper::SORT_ORDER_ASC]]),
                'campusesSelected' => $campusesSelected
            ],
            'Admin - Top Menu'
        );
    }

    /**
     * Display create screen
     *W
     * @api   GET /adminv2/insert
     * @since 165
     *
     */
    public function getCreate()
    {
        $this->response = $this->responseFromTemplate(
            '@adminMenuTop/modify.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminMenuTop.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminMenuTop.getIndex')),
                    [['Create', null]]
                ),
                'icons' => $this->getMenuIconMapper()->findAll(['order' => ['title' => BaseMapper::SORT_ORDER_ASC]]),
                'roles' => $this->getRoleMapper()->findAll(['order' => ['sequence' => BaseMapper::SORT_ORDER_ASC]]),
                'campuses' => $this->getCampusMapper()->findAll(['order' => ['sequence' => BaseMapper::SORT_ORDER_ASC]])
            ],
            'Admin - Top Menu'
        );
    }

    /**
     * Update menu item order
     *
     * @api   POST /adminv2/menu-top/order
     * @since 165
     */
    public function postOrder()
    {
        $post = $this->requestPost;
        $orderArray = explode(",", $post['order']);

        $order = 1;

        foreach ($orderArray as $id) {
            try {
                /** @var MenuTop $item */
                $item = $this->getMenuTopMapper()->findById($id);
                $item->setSortOrder($order++);
                $this->getMenuTopMapper()->save($item);
            } catch (MapperException $e) {
            }
        }
    }

    /**
     * Delete menu item
     *
     * @api   POST /adminv2/menu-top/delete
     * @since 165
     */
    public function postDelete()
    {
        $post = $this->requestPost;
        $this->getMenuTopMapper()->deleteByColumn("id", $post["id"]);
    }

    /**
     * Save menu item
     *
     * @api   POST /adminv2/menu-top/modify
     * @since 165
     */
    public function postModify()
    {
        $post = $this->requestPost;

        if (!empty($post->get('id'))) {
            $item = $this->getMenuTopMapper()->findById($post->get('id'));
        } else {
            $item = new MenuTop();
            $item->setPlanHide(false);

            $itemOrder = $this->getMenuTopMapper()->findAll([
                'limit' => 1,
                'order' => [
                    'sort_order' => BaseMapper::SORT_ORDER_DESC
                ]
            ]);

            if ($itemOrder->count()) {
                $item->setSortOrder($itemOrder->first()->getSortOrder() + 1);
            } else {
                $item->setSortOrder(1);
            }
        }

        $item->setTitle($post->get('title'));
        $item->setText($post->get('text'));
        $item->setIcon($post->get('icon'));
        $item->setUrl($post->get('url'));
        $item->setTarget($post->get('target'));

        // Populate token
        $url = $post->get('url');
        if (stripos($url, '/learning/classes') === 0) {
            $item->setToken('CLASSES');
        } elseif (stripos($url, '/learning/due') === 0) {
            $item->setToken('SUBMISSION');
        } elseif (stripos($url, '/timetable') === 0) {
            $item->setToken('TIMETABLE');
        } elseif (stripos($url, '/calendar/week') === 0) {
            $item->setToken('CALENDAR');
        } elseif (stripos($url, '/news') === 0) {
            $item->setToken('NEWS');
        } elseif (stripos($url, '/learning/grades') === 0) {
            $item->setToken('GRADE');
        } elseif (stripos($url, '/course/list') === 0) {
            $item->setToken('COURSE');
        } elseif (stripos($url, '/resources') === 0) {
            $item->setToken('RESOURCES');
        } elseif (stripos($url, '/eportfolio') === 0) {
            $item->setToken('EPORTFOLIO');
        } elseif (stripos($url, '/my-files') === 0) {
            $item->setToken('MYFILES');
        } elseif (stripos($url, '/drives') === 0) {
            $item->setToken('NETWORK');
        } else {
            $item->setToken('');
        }

        $this->getMenuTopMapper()->save($item);

        $this->getMenuTopCampusMapper()->deleteByColumn('menu_top_id', $item->getId());

        if (is_array($post->get('campuses'))) {
            foreach ($post->get('campuses') as $campusId) {
                $menuTopCampus = new MenuTopCampus();
                $menuTopCampus->setMenuTopId($item->getId());
                $menuTopCampus->setCampusId($campusId);
                $this->getMenuTopCampusMapper()->save($menuTopCampus);
            }
        }

        $this->getMenuTopRoleMapper()->deleteByColumn('menu_top_id', $item->getId());

        if (is_array($post->get('roles'))) {
            foreach ($post->get('roles') as $roleId) {
                $menuTopRole = new MenuTopRole();
                $menuTopRole->setMenuTopId($item->getId());
                $menuTopRole->setRoleId($roleId);
                $this->getMenuTopRoleMapper()->save($menuTopRole);
            }
        }

        $this->response = $this->redirectAfterPost("/adminv2/menu-top");
    }

    /** Here be mappers and stuff */

    /**
     * @return MenuIconMapper
     */
    public function getMenuIconMapper()
    {
        return $this->menuIconMapper;
    }

    /**
     * @param MenuIconMapper $mapper
     * @return $this
     */
    public function setMenuIconMapper($mapper)
    {
        $this->menuIconMapper = $mapper;
        return $this;
    }

    // Top menu mapper

    /**
     * @return MenuTopMapper
     */
    public function getMenuTopMapper()
    {
        return $this->menuTopMapper;
    }

    /**
     * @param MenuTopMapper $mapper
     * @return $this
     */
    public function setMenuTopMapper($mapper)
    {
        $this->menuTopMapper = $mapper;
        return $this;
    }

    /**
     * @return MenuTopCampusMapper
     */
    public function getMenuTopCampusMapper()
    {
        return $this->menuTopCampusMapper;
    }

    /**
     * @param MenuTopCampusMapper $mapper
     * @return $this
     */
    public function setMenuTopCampusMapper($mapper)
    {
        $this->menuTopCampusMapper = $mapper;
        return $this;
    }

    /**
     * @return MenuTopRoleMapper
     */
    public function getMenuTopRoleMapper()
    {
        return $this->menuTopRoleMapper;
    }

    /**
     * @param MenuTopRoleMapper $mapper
     * @return $this
     */
    public function setMenuTopRoleMapper($mapper)
    {
        $this->menuTopRoleMapper = $mapper;
        return $this;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * @param RoleMapper $mapper
     * @return $this
     */
    public function setRoleMapper($mapper)
    {
        $this->roleMapper = $mapper;
        return $this;
    }

    /**
     * @return CampusMapper
     */
    public function getCampusMapper()
    {
        return $this->campusMapper;
    }

    /**
     * @param CampusMapper $mapper
     * @return $this
     */
    public function setCampusMapper($mapper)
    {
        $this->campusMapper = $mapper;
        return $this;
    }
}
