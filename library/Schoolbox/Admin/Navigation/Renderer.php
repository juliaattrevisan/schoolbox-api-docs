<?php
/**
  * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Admin\Navigation;

use Schoolbox\Config\Loader;

/**
 * Class Renderer
 *
 * Share navigation element for admin pages
 *
 * @package Schoolbox\Admin\Navigation
 */
class Renderer extends \Twig_Extension
{
    /**
     * @return array|\Twig_Function[]
     */
    public function getFunctions(): array
    {
        return array(
            new \Twig_SimpleFunction('renderAdminNavigation', [$this, 'renderAdminNavigation'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
        );
    }

    /**
     * @param \Twig_Environment $twig
     * @param                   $pageCurrent
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function renderAdminNavigation(\Twig_Environment $twig, $pageCurrent): string
    {
        // Grab navigation items from old admin class
        // TODO figure out if this class is even needed: either way, inject this
        $nav = new \AdminV2_Nav(new Loader());

        // Render this thing out

        return $twig->render('@adminNavigation/index.twig', array(
            'pageCurrent' => $pageCurrent,
            'links' => $nav->getNavigation()
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'admin_navigation_renderer';
    }
}
