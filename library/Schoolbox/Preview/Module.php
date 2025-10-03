<?php

/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Preview;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Map;
use Alaress\Web\Router\PathMatcher;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    protected $basename = 'preview';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(string $basePath)
    {
        /* Render html fragment, or redirect
         */
        $this->addGetRoute('preview', $basePath, '/{encodedUrl}')
            ->attributes([
                'format' => '.json'
            ]);

        // Allow this to match anything after /pdfjs/, no matter how many path attrs
        $this->addGetRoute('pdfJs', $basePath, '/pdfjs/')
             ->wildcard('pathParts');
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '(?:index|preview)', new Rule\IsLoggedIn());
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:index|preview)',
            function ($params) {
                return array(
                    'params' => $params
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }
}
