<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Navigation;

use Alaress\Authorizer\Authorizer;
use Schoolbox\Config\Loader;
use Schoolbox\User;
use Twig\Environment;
use Twig\Extension;
use Twig\TwigFunction;

/**
 * Class Renderer
 *
 * Share navigation element for admin pages
 *
 * @package Schoolbox\Admin\Navigation
 */
class Renderer extends Extension\AbstractExtension
{
    public function __construct(private readonly Authorizer $authorizer, private readonly User $loggedInUser)
    {
    }

    /**
     * @return array|TwigFunction[]
     */
    public function getFunctions(): array
    {
        return array(
            new TwigFunction('renderAdminNavigation', [$this, 'renderAdminNavigation'], [
                'is_safe' => ['html'],
                'needs_environment' => true
            ]),
        );
    }

    /**
     * @param Environment $twig
     * @param string      $pageCurrent
     * @param string|null $title this title will be used on the page, if provided
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderAdminNavigation(Environment $twig, string $pageCurrent, ?string $title = null): string
    {
        // Grab navigation items from old admin class
        // TODO SA-8354: inject this

        // Render this thing out
        return (new Subnav(new Loader(), $this->authorizer, $this->loggedInUser))->generateHtml($pageCurrent, $title);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'admin_navigation_renderer';
    }
}
