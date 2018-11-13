<?php
/**
 * Top menu renderer
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Menu\Top;

use Schoolbox\User;
use Schoolbox\Menu\Top\Mapper as MenuTopMapper;
use Schoolbox\Menu\Top\Model as MenuTopModel;
use Schoolbox\Mapper\Exception as MapperException;

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
        try {
            $tabs = $this->getMenuTopMapper()->findTabsForUser($user);
        } catch (MapperException $e) {
            $tabs = [];
        }

        // Render whatever tabs passed muster

        return $twig->render('@menuTop/renderer.twig', array(
            'tabCurrent' => $tabCurrent,
            'tabs'       => $tabs,
            'user'       => $user,
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
