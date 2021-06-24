<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\IConfigLoader;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Class Subnav
 *
 * An emulation of Admin Side Navigation that automatically generates the
 * hierarchy based on the Pastoral Types and SubTypes that currently exist
 *
 * @package Schoolbox\Pastoral\Audience
 */
class Subnav
{

    /**
     * For access to booleans that determine availability/visibility of certain menu
     * items based on plan, configuration, etc.
     *
     * @var IConfigLoader
     */
    private IConfigLoader $configLoader;


    /**
     * A twig rendering environment. I know it's spawned in the constructor, but half
     * the screens use phplib and the other half use twig
     *
     * @var Environment
     */
    private Environment $twig;


    /**
     * Subnav constructor.
     *
     * @param IConfigLoader $configLoader
     * @throws \LogicException
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;

        $paths[] = SITE_root . '/library/Schoolbox/Pastoral/Audience/templates';
        $paths[] = SITE_root . '/templates/core/sub';

        $options = [
            'debug'            => $this->configLoader->get('debug_templates'),
            'strict_variables' => $this->configLoader->get('strict_templates'),
        ];

        // do not cache when in debug mode
        if (!$this->configLoader->get('debug_mode')) {
            $options['cache'] = $this->configLoader->get('twig_cache_dir');
        }

        $this->twig = new Environment(new FilesystemLoader($paths), $options);

        // The CDN_PREFIX needs to be made available in this Twig instance
        $this->twig->addGlobal('CDN_PREFIX', (new PrefixGenerator($this->configLoader))->generate());
    }


    /**
     * Render the self-contained subnav as a purely html fragment
     * so you can slam it straight into a phplib sigh
     *
     * @param string $url
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateHtml(string $url): string
    {

        /* Look ahead for $url
         * If it's in a child node, remember its parent and pass that too
         */
        $pageCurrent = '';
        $subpageCurrent = '';

        foreach ($this->generate() as $parentUrl => $parentData) {
            if ($parentUrl === $url) {
                $pageCurrent = $parentUrl;
            }

            if (\count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {
                        $pageCurrent = $parentUrl;
                        $subpageCurrent = $childUrl;
                    }
                }
            }
        }

        return $this->twig->render('subnav.twig', [
            'adminSubnav' => $this->generate(),
            'pageCurrent' => $pageCurrent,
            'subpageCurrent' => $subpageCurrent
        ]);
    }


    /**
     * Generate a list of tuples that represent the Pastoral Type and Subtype hierarchy
     * in more or less the same way as the admin subnavigation
     *
     * @return array
     */
    public function generate(): array
    {
        return [
            'url_academic' => [
                'name' => 'Academic',
                'visible' => true,
                'description' => 'Academic',
                'children' => [
                    'url_awardachievement' => [
                        'name' => 'Award/Achievement',
                        'visible' => true,
                        'description' => 'Award/Achievement',
                        'children' => []
                    ],
                ]
            ],
            'url_discipline' => [
                'name' => 'Discipline',
                'visible' => true,
                'description' => 'Discipline',
                'children' => [
                    'url_behaviouralissues' => [
                        'name' => 'Behavioural Issues',
                        'visible' => true,
                        'description' => 'Behavioural Issues',
                        'children' => []
                    ],
                ]
            ],
        ];
    }
}