<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Menu\Side;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\GenericCollection;
use Schoolbox\Menu\Side\Campus\Mapper as MenuSideCampusMapper;
use Schoolbox\Menu\Side\Role\Mapper as MenuSideRoleMapper;
use Schoolbox\Menu\Side\Section\Mapper as MenuSideSectionMapper;
use Schoolbox\Menu\Side\Campus\Model as MenuSideCampus;
use Schoolbox\Menu\Side\Role\Model as MenuSideRole;
use Schoolbox\Menu\Side\Section\Model as MenuSideSection;
use Schoolbox\Menu\Side\Mapper as MenuSideMapper;
use Schoolbox\Menu\Side\Model as MenuSide;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terms
 */
class Controller extends Base
{
    /** @var MenuSideMapper $menuSideMapper */
    private $menuSideMapper;

    /** @var RoleMapper $roleMapper */
    private $roleMapper;

    /** @var CampusMapper $campusMapper */
    private $campusMapper;

    /** @var MenuSideCampusMapper $menuSideCampusMapper */
    private $menuSideCampusMapper;

    /** @var MenuSideRoleMapper $menuSideRoleMapper */
    private $menuSideRoleMapper;

    /** @var MenuSideSectionMapper $menuSideSectionMapper */
    private $menuSideSectionMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setMenuSideMapper(MenuSideMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setCampusMapper(CampusMapper::getInstance());
        $this->setMenuSideCampusMapper(MenuSideCampusMapper::getInstance());
        $this->setMenuSideRoleMapper(MenuSideRoleMapper::getInstance());
        $this->setMenuSideSectionMapper(MenuSideSectionMapper::getInstance());
    }

    /**
     * Display tree
     *
     * @api   GET /adminv2/menu-side
     * @since 165
     */
    public function getIndex()
    {
        // Grab all the menu items

        /** @var MenuSide[]|GenericCollection $sideMenus */
        $sideMenus = $this->getMenuSideMapper()->findAll()->sortBy('sortOrder', 'asc');

        // Populate kids where appropriate

        $treeList = new Collection();

        foreach ($sideMenus as $node) {
            $nodeId   = $node->getId();
            $parentId = $node->getParent()->getId();

            if (!$parentId) {
                $treeList->add($node);
            }

            $node->setChildren($sideMenus->filterBy('parentId', $nodeId));
        }

        // Create root node

        $base = new MenuSide();
        $base
            ->setId(0)
            ->setTitle('Top Level')
            ->setChildren($treeList);

        $tree = new Collection();
        $tree->add($base);

        // Create the rest of the tree underneath the root

        $treeList = $this->renderTree($tree);

        $sections = $this->getMenuSideSectionMapper()->findAll()->sortBy('sortOrder', 'asc');

        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminMenuSide/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminMenuSide.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminMenuSide.getIndex')),
                'tree' => $treeList,
                'sections' => $sections,
                'configTitle' => $this->getConfigLoader()->get('title'),
                'sectionName' => MenuSideSectionMapper::getSectionNameDefaults()
            ],
            'Admin - Side Menu'
        );
    }

    /**
     * Recursively build tree HTML
     * @param Collection $tree
     * @param bool $selectable
     * @param int $level
     * @return string
     */
    private function renderTree(Collection $tree, $selectable = false, $level = 0)
    {
        if ($level > 50) {
            return '';
        }

        $text = "<ul>\n";

        foreach ($tree as $menuItem) {
            $id       = $menuItem->getId();
            $menuText = \Scrub::text($menuItem->getTitle());
            $children = $menuItem->getChildren();

            $text .= '<li id="node_' . $id . '">';

            if ($selectable || $id == 0) {
                $text .= '<a id="' . $id . '" href="#">' . $menuText . "</a>";
            } else {
                $text .= '<a href="/adminv2/menu-side/modify/' . $id . '">' . $menuText . '</a>';
            }

            if (count($children)) {
                $text .= $this->renderTree($children, $selectable, ($level + 1));
            }

            $text .= "</li>\n";
        }

        $text .= "</ul>\n";

        return $text;
    }

    /**
     * Display modification screen
     *
     * @api   GET /adminv2/menu-side/modify/{id}
     * @since 165
     *
     * @param int $id
     */
    public function getModify($id)
    {
        // Get selected campuses and roles

        $selections = $this->getMenuSideRoleMapper()->findByColumn('menu_side_id', $id);

        $rolesSelected = [];

        /** @var MenuSideRole $selection */
        foreach ($selections as $selection) {
            $rolesSelected[$selection->getRoleId()] = true;
        }

        $selections = $this->getMenuSideCampusMapper()->findByColumn('menu_side_id', $id);

        $campusesSelected = [];

        /** @var MenuSideCampus $selection */
        foreach ($selections as $selection) {
            $campusesSelected[$selection->getCampusId()] = true;
        }

        // Render this mess out
        $this->response = $this->responseFromTemplate(
            '@adminMenuSide/modify.twig',
            [
                'resource' => $this->getDi()->get('sitePage')->getLocalisedTerm('resource', true, true),
                'pageCurrent' => $this->routeGenerator->generate('adminMenuSide.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminMenuSide.getIndex')),
                    [['Modify', null]]
                ),
                'item' => $this->getMenuSideMapper()->findById($id),
                'roles' => $this->getRoleMapper()->findAll()->sortBy('name', 'asc'),
                'rolesSelected' => $rolesSelected,
                'campuses' => $this->getCampusMapper()->findAll()->sortBy('name', 'asc'),
                'campusesSelected' => $campusesSelected
            ],
            'Admin - Side Menu'
        );
    }

    /**
     * Display create screen
     *
     * @api   GET /adminv2/menu-side/insert
     * @since 165
     */
    public function getCreate()
    {
        $this->response = $this->responseFromTemplate(
            '@adminMenuSide/modify.twig',
            [
                'resource' => $this->getDi()->get('sitePage')->getLocalisedTerm('resource', true, true),
                'pageCurrent' => $this->routeGenerator->generate('adminMenuSide.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminMenuSide.getIndex')),
                    [['Create', null]]
                ),
                'roles' => $this->getRoleMapper()->findAll()->sortBy('name', 'asc'),
                'campuses' => $this->getCampusMapper()->findAll()->sortBy('name', 'asc')
            ],
            'Admin - Side Menu',
        );
    }

    /**
     * Save menu item
     *
     * @api   POST /adminv2/menu-side/modify
     * @since 165
     */
    public function postModify()
    {
        $post = $this->requestPost;

        if (!empty($post->get('id'))) {
            $item = $this->getMenuSideMapper()->findById($post->get('id'));
        } else {
            $item = new MenuSide();

            $itemOrder = $this->getMenuSideMapper()->findAll()->sortBy('sortOrder', 'desc')->first();
            $item->setParent((new Menuside())->setId(0));
            $item->setSortOrder($itemOrder->getSortOrder() + 1);
        }

        $item->setTitle($post->get('title'));
        $item->setUrl(trim($post->get('url')));
        $item->setTarget($post->get('target'));
        $this->getMenuSideMapper()->save($item);

        $this->getMenuSideCampusMapper()->deleteByColumn('menu_side_id', $item->getId());

        if (is_array($post->get('campuses'))) {
            foreach ($post->get('campuses') as $campusId) {
                $menuSideCampus = new MenuSideCampus();
                $menuSideCampus->setMenuSideId($item->getId());
                $menuSideCampus->setCampusId($campusId);
                $this->getMenuSideCampusMapper()->save($menuSideCampus);
            }
        }

        $this->getMenuSideRoleMapper()->deleteByColumn('menu_side_id', $item->getId());

        if (is_array($post->get('roles'))) {
            foreach ($post->get('roles') as $roleId) {
                $menuSideRole = new MenuSideRole();
                $menuSideRole->setMenuSideId($item->getId());
                $menuSideRole->setRoleId($roleId);
                $this->getMenuSideRoleMapper()->save($menuSideRole);
            }
        }

        $this->response = $this->redirectAfterPost("/adminv2/menu-side");
    }


    /**
     * Save menu item
     *
     * @api   POST /adminv2/menu-side/modify-section/{sectionId}/{field}
     * @since 165
     *
     * @param int    $sectionId
     * @param string $field
     */
    public function postSectionModify($sectionId, $field)
    {
        $post = $this->requestPost;

        try {
            /** @var MenuSideSection $section */
            $section = $this->getMenuSideSectionMapper()->findOneByColumn('section', $sectionId);
            switch ($field) {
                case 'title':
                    if (empty($post['name'])) {
                        $post['name'] = MenuSideSectionMapper::getSectionNameDefaults()[$sectionId];
                    }
                    $section->setTitle($post['name']);
                    break;

                case 'visible':
                    $section->setVisible($post['name']);
                    break;
            }

            $this->getMenuSideSectionMapper()->save($section);

            $this->response = $this->responseAsJson([
                'result' => 'OK'
            ])->withStatus(200);
        } catch (MapperException $e) {
            $this->response = $this->responseAsJson([
                'result' => 'Error updating record'
            ])->withStatus(500);
        }
    }

    /**
     * Delete menu item
     *
     * @api   POST /adminv2/menu-side/delete
     * @since 165
     */
    public function postDelete()
    {
        $post = $this->requestPost;
        $this->getMenuSideMapper()->deleteByColumn("id", $post["id"]);
        $this->response = $this->responseAsJson([])->withStatus(204);
    }

    /**
     * Update menu item order
     *
     * @api   POST /adminv2/menu-side/order
     * @since 165
     */
    public function postOrder()
    {
        $post = $this->requestPost;

        try {
            /** @var MenuSide $item */
            $item = $this->getMenuSideMapper()->findById($post['id']);
            $item->setParent((new MenuSide())->setId($post['parentId']));
            $this->getMenuSideMapper()->save($item);

            $parentChildren = $this->getMenuSideMapper()->findByColumn('parent_id', $post['parentId'], ['order' => ['sort_order' => 'asc']]);

            $orderCurrent = 0;

            /** @var MenuSide $node */
            foreach ($parentChildren as $node) {
                if ($orderCurrent == $post['position']) {
                    $orderCurrent++;
                }

                if ($node->getId() == $post['id']) {
                    $node->setSortOrder($post['position']);
                } else {
                    $node->setSortOrder($orderCurrent++);
                }

                $this->getMenuSideMapper()->save($node);
            }
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(['result' => 'Error']);
        }

        $this->response = $this->responseAsJson(['result' => 'OK']);
    }

    /**
     * Update menu item order
     *
     * @api   POST /adminv2/menu-side/order-section
     * @since 165
     */
    public function postSectionOrder()
    {
        $post = $this->requestPost;
        $orderArray = explode(",", $post['order']);

        $order = 1;

        foreach ($orderArray as $id) {
            try {
                /** @var MenuSideSection $item */
                $item = $this->getMenuSideSectionMapper()->findOneByColumn('section', $id);
                $item->setSortOrder($order++);
                $this->getMenuSideSectionMapper()->save($item);
            } catch (MapperException $e) {
            }
        }
    }

    /** Here be mappers and stuff */

    /**
     * @return MenuSideMapper
     */
    public function getMenuSideMapper()
    {
        return $this->menuSideMapper;
    }

    /**
     * @param MenuSideMapper $mapper
     * @return $this
     */
    public function setMenuSideMapper($mapper)
    {
        $this->menuSideMapper = $mapper;
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


    /**
     * @return MenuSideCampusMapper
     */
    public function getMenuSideCampusMapper()
    {
        return $this->menuSideCampusMapper;
    }

    /**
     * @param MenuSideCampusMapper $mapper
     * @return $this
     */
    public function setMenuSideCampusMapper($mapper)
    {
        $this->menuSideCampusMapper = $mapper;
        return $this;
    }

    /**
     * @return MenuSideRoleMapper
     */
    public function getMenuSideRoleMapper()
    {
        return $this->menuSideRoleMapper;
    }

    /**
     * @param MenuSideRoleMapper $mapper
     * @return $this
     */
    public function setMenuSideRoleMapper($mapper)
    {
        $this->menuSideRoleMapper = $mapper;
        return $this;
    }

    /**
     * @return MenuSideSectionMapper
     */
    public function getMenuSideSectionMapper()
    {
        return $this->menuSideSectionMapper;
    }

    /**
     * @param MenuSideSectionMapper $mapper
     * @return $this
     */
    public function setMenuSideSectionMapper($mapper)
    {
        $this->menuSideSectionMapper = $mapper;
        return $this;
    }
}
