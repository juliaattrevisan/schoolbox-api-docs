<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Menu\Top;

use Alaress\Web\Controller\Base;
use Schoolbox\Menu\Icon\Mapper as MenuIconMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Top\Campus\Mapper as MenuTopCampusMapper;
use \Schoolbox\Menu\Top\Group\Mapper as MenuTopGroupMapper;
use Schoolbox\Menu\Top\Campus\Model as MenuTopCampus;
use Schoolbox\Menu\Top\Group\Model as MenuTopGroup;
use Schoolbox\Menu\Top\Mapper as MenuTopMapper;
use Schoolbox\Menu\Top\Model as MenuTop;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Menu\Top
 */
class Controller extends Base
{
    public function __construct()
    {
        $this->setMenuIconMapper(MenuIconMapper::getInstance());
        $this->setMenuTopMapper(MenuTopMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setCampusMapper(CampusMapper::getInstance());
        $this->setMenuTopCampusMapper(MenuTopCampusMapper::getInstance());
        $this->setMenuTopGroupMapper(MenuTopGroupMapper::getInstance());
    }

    /**
     * Display re-order screen
     */
    public function getIndex()
    {
        $this->getResponse()->content->set([
            'template' => '@adminMenuTop/index.twig',
            'data'     => [
                'pageCurrent' => basename($this->getRequest()->query->get('page')),
                'menu' => $this->getMenuTopMapper()->findAll()->sortBy('sortOrder', 'asc')
            ],
            'title' => 'Admin - Top Menu'
        ]);
    }

    /**
     * Display modification screen
     * @param $id
     */
    public function getModify($id)
    {
        // Get selected campuses and groups

        $selections = $this->getMenuTopGroupMapper()->findByColumn('menu_top_id', $id);

        $groupsSelected = [];

        /** @var MenuTopGroup $selection */
        foreach ($selections as $selection) {
            $groupsSelected[$selection->getGroupId()] = true;
        }

        $selections = $this->getMenuTopCampusMapper()->findByColumn('menu_top_id', $id);

        $campusesSelected = [];

        /** @var MenuTopCampus $selection */
        foreach ($selections as $selection) {
            $campusesSelected[$selection->getCampusId()] = true;
        }
        
        $this->getResponse()->content->set([
            'template' => '@adminMenuTop/modify.twig',
            'data'     => [
                'pageCurrent' => basename($this->getRequest()->query->get('page')),
                'item' => $this->getMenuTopMapper()->findById($id),
                'icons' => $this->getMenuIconMapper()->findAll()->sortBy('title', 'asc'),
                'groups' => $this->getRoleMapper()->findAll()->sortBy('name', 'asc'),
                'groupsSelected' => $groupsSelected,
                'campuses' => $this->getCampusMapper()->findAll()->sortBy('name', 'asc'),
                'campusesSelected' => $campusesSelected                
            ],
            'title' => 'Admin - Top Menu'
        ]);
    }

    /**
     * Display create screen
     */
    public function getInsert()
    {
        $this->getResponse()->content->set([
            'template' => '@adminMenuTop/modify.twig',
            'data'     => [
                'pageCurrent' => basename($this->getRequest()->query->get('page')),
                'icons' => $this->getMenuIconMapper()->findAll()->sortBy('title', 'asc'),
                'groups' => $this->getRoleMapper()->findAll()->sortBy('name', 'asc'),
                'campuses' => $this->getCampusMapper()->findAll()->sortBy('name', 'asc')
            ],
            'title' => 'Admin - Top Menu'
        ]);
    }

    /**
     * Update menu item order
     */
    public function postOrder()
    {
        $post = $this->getRequest()->post;
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
     */
    public function postDelete()
    {
        $post = $this->getRequest()->post;
        $this->getMenuTopMapper()->deleteByColumn("id", $post["id"]);
    }

    /**
     * Save menu item
     */
    public function postModify()
    {
        $post = $this->getRequest()->post;

        if (!empty($post['id'])) {
            $item = $this->getMenuTopMapper()->findById($post['id']);
        } else {
            $item = new MenuTop();

            $itemOrder = $this->getMenuTopMapper()->findAll()->sortBy('sortOrder', 'desc')->first();
            $item->setSortOrder($itemOrder->getSortOrder() + 1);
        }

        $item->setTitle($post['title']);
        $item->setText($post['text']);
        $item->setIcon($post['icon']);
        $item->setUrl($post['url']);
        $item->setTarget($post['target']);
        $this->getMenuTopMapper()->save($item);

        $this->getMenuTopCampusMapper()->deleteByColumn('menu_top_id', $item->getId());

        if (is_array($post['campuses'])) {
            foreach ($post['campuses'] as $campusId) {
                $menuTopCampus = new MenuTopCampus();
                $menuTopCampus->setMenuTopId($item->getId());
                $menuTopCampus->setCampusId($campusId);
                $this->getMenuTopCampusMapper()->save($menuTopCampus);
            }
        }
        
        $this->getMenuTopGroupMapper()->deleteByColumn('menu_top_id', $item->getId());

        if (is_array($post['groups'])) {
            foreach ($post['groups'] as $groupId) {
                $menuTopGroup = new MenuTopGroup();
                $menuTopGroup->setMenuTopId($item->getId());
                $menuTopGroup->setGroupId($groupId);
                $this->getMenuTopGroupMapper()->save($menuTopGroup);
            }
        }
        
        $this->getResponse()->redirect->afterPost("/adminv2/menu-top");
    }

    /** Here be mappers and stuff */

    /** @var MenuIconMapper $menuIconMapper */
    private $menuIconMapper;

    /**
     * @return MenuIconMapper
     */
    public function getMenuIconMapper() {
        return $this->menuIconMapper;
    }

    /**
     * @param $mapper
     * @return $this
     */
    public function setMenuIconMapper($mapper) {
        $this->menuIconMapper = $mapper;
        return $this;
    }

    // Top menu mapper

    /** @var MenuTopMapper $menuTopMapper */
    private $menuTopMapper;

    /**
     * @return MenuTopMapper
     */
    public function getMenuTopMapper() {
        return $this->menuTopMapper;
    }

    /**
     * @param $mapper
     * @return $this
     */
    public function setMenuTopMapper($mapper) {
        $this->menuTopMapper = $mapper;
        return $this;
    }

    /** @var MenuTopCampusMapper $menuTopCampusMapper */
    private $menuTopCampusMapper;

    /**
     * @return MenuTopCampusMapper
     */
    public function getMenuTopCampusMapper() {
        return $this->menuTopCampusMapper;
    }

    /**
     * @param $mapper
     * @return $this
     */
    public function setMenuTopCampusMapper($mapper) {
        $this->menuTopCampusMapper = $mapper;
        return $this;
    }

    /** @var MenuTopGroupMapper $menuTopGroupMapper */
    private $menuTopGroupMapper;

    /**
     * @return MenuTopGroupMapper
     */
    public function getMenuTopGroupMapper() {
        return $this->menuTopGroupMapper;
    }

    /**
     * @param $mapper
     * @return $this
     */
    public function setMenuTopGroupMapper($mapper) {
        $this->menuTopGroupMapper = $mapper;
        return $this;
    }

    /** @var RoleMapper $roleMapper */
    private $roleMapper;

    /**
     * @return RoleMapper
     */
    public function getRoleMapper() {
        return $this->roleMapper;
    }

    /**
     * @param $mapper
     * @return $this
     */
    public function setRoleMapper($mapper) {
        $this->roleMapper = $mapper;
        return $this;
    }

    /** @var CampusMapper $campusMapper */
    private $campusMapper;

    /**
     * @return CampusMapper
     */
    public function getCampusMapper() {
        return $this->campusMapper;
    }

    /**
     * @param $mapper
     * @return $this
     */
    public function setCampusMapper($mapper) {
        $this->campusMapper = $mapper;
        return $this;
    }
}
