<?php
/**
 * Share navigation element for admin pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Navigation;

class Renderer extends \Twig_Extension
{
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('renderAdminNavigation', [$this, 'renderAdminNavigation'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
        );
    }

    public function renderAdminNavigation(\Twig_Environment $twig, $pageCurrent)
    {
        // Grab navigation items from old admin class

        $nav = new \AdminV2_Nav();

        // Render this thing out

        return $twig->render('@adminNavigation/index.twig', array(
            'pageCurrent' => $pageCurrent,
            'links' => $nav->getNavigation()
        ));
    }

    public function getName()
    {
        return 'admin_navigation_renderer';
    }
}