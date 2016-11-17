<?php
/**
 * Top menu renderer
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Menu\Top;

use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Menu\Top\Mapper as MenuTopMapper;
use Schoolbox\Menu\Top\Model as MenuTopModel;
use Schoolbox\Menu\Top\Campus\Mapper as MenuTopCampusMapper;
use Schoolbox\Menu\Top\Campus\Model as MenuTopCampusModel;
use Schoolbox\Menu\Top\Role\Mapper as MenuTopRoleMapper;
use Schoolbox\Menu\Top\Role\Model as MenuTopRoleModel;

class Renderer extends \Twig_Extension
{
    /** @var MenuTopMapper */
    protected $menuTopMapper;

    /** @var MenuTopCampusMapper */
    protected $menuTopCampusMapper;

    /** @var MenuTopRoleMapper */
    protected $menuTopRoleMapper;

    /**
     * Renderer constructor
     */
    public function __construct()
    {
        $this->setMenuTopMapper(MenuTopMapper::getInstance());
        $this->setMenuTopCampusMapper(MenuTopCampusMapper::getInstance());
        $this->setMenuTopRoleMapper(MenuTopRoleMapper::getInstance());
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('menuTopRenderer', [$this, 'menuTopRenderer'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
        );
    }

    /**
     * @param \Twig_Environment $twig
     * @param string $tabCurrent Used to highlight active tab
     * @param User $user Current logged in users details
     * @return string
     */
    public function menuTopRenderer(\Twig_Environment $twig, $tabCurrent = null, $user = null)
    {
        // Render this thing out

        $tabsRendered = new Collection;
        $tabs = $this->getMenuTopMapper()->findAll()->sortBy('sortOrder', 'asc');

        /** @var MenuTopModel $tab */
        foreach ($tabs as $tab) {
            // Check visibility

            // Campuses

            $foundCampus = false;

            $campuses = $this->getMenuTopCampusMapper()->findByColumn('menu_top_id', $tab->getId());

            if ($campuses->count() > 0) {

                /** @var MenuTopCampusModel $campus */
                foreach ($campuses as $campus) {

                    /** @var \Schoolbox\Campus $userCampus */
                    foreach ($user->getCampusCollection() as $userCampus) {
                        if ($campus->getCampusId() == $userCampus->getId()) {
                            $foundCampus = true;
                            break;
                        }
                    }

                    if ($foundCampus) {
                        break;
                    }
                }
            }

            // Campus wasn't found, skip to the next tab

            if (!$foundCampus) {
                continue;
            }

            // Groups

            $foundGroup = false;

            $roles = $this->getMenuTopRoleMapper()->findByColumn('menu_top_id', $tab->getId());

            if ($roles->count() > 0) {

                /** @var MenuTopRoleModel $role */
                foreach ($roles as $role) {

                    if ($role->getRoleId() == $user->getRole()->getId()) {
                        $foundGroup = true;
                        break;
                    }
                }

            }

            if (!$foundGroup) {
                continue;
            }

            // Primitive tokenization for URLs, allows injecting user IDs and possibly other variables

            $array_find = [
                '[id]',
                '[externalId]',
                '[username]',
                '[firstName]',
                '[lastName]',
            ];

            $array_replace = [
                $user->getId(),
                $user->getExternalId(),
                $user->getUsername(),
                $user->getFirstname(),
                $user->getLastname(),
            ];

            $tab->setUrl(str_ireplace($array_find, $array_replace, $tab->getUrl()));
            $tab->setText(str_ireplace($array_find, $array_replace, $tab->getText()));
            $tab->setTitle(str_ireplace($array_find, $array_replace, $tab->getTitle()));

            // Add the tab to the list that will be rendered

            $tabsRendered->add($tab);
        }

        // Render whatever tabs passed muster

        return $twig->render('@menuTop/renderer.twig', array(
            'tabCurrent' => $tabCurrent,
            'tabs' => $tabsRendered
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'menu_top_renderer';
    }

    /** Getters and settings ahoy */

    /**
     * @return MenuTopMapper
     */
    public function getMenuTopMapper()
    {
        return $this->menuTopMapper;
    }

    /**
     * @param MenuTopMapper $menuTopMapper
     * @return $this
     */
    public function setMenuTopMapper($menuTopMapper)
    {
        $this->menuTopMapper = $menuTopMapper;
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
     * @param MenuTopRoleMapper $menuTopRoleMapper
     * @return $this
     */
    public function setMenuTopRoleMapper($menuTopRoleMapper)
    {
        $this->menuTopRoleMapper = $menuTopRoleMapper;
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
     * @param MenuTopCampusMapper $menuTopCampusMapper
     * @return $this
     */
    public function setMenuTopCampusMapper($menuTopCampusMapper)
    {
        $this->menuTopCampusMapper = $menuTopCampusMapper;
        return $this;
    }
}