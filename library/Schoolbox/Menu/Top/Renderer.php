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

class Renderer
{
    /** @var MenuTopMapper */
    protected $menuTopMapper;

    /**
     * Renderer constructor
     */
    public function __construct()
    {
        $this->setMenuTopMapper(MenuTopMapper::getInstance());
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

        $tabs = $this->getMenuTopMapper()->findTabsForUser($user);

        /** @var MenuTopModel $tab */
        foreach ($tabs as $tab) {
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
        }

        // Render whatever tabs passed muster

        return $twig->render('@menuTop/renderer.twig', array(
            'tabCurrent' => $tabCurrent,
            'tabs' => $tabs
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


}