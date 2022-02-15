<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Help\Marketo\Component\Dashboard;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Component_Instance;
use Psr\Container\ContainerInterface;
use Schoolbox\User;
use SitePage;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'marketoDashboard';

    /**
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): ?Base
    {
        // TODO is this even a real controller, or is it just a component?
        return null;
//        /** @var SitePage $sitePage */
//        $sitePage = $container->get('sitePage');
//
//        /** @var User $user */
//        $user = $container->get('user');
//        return new Controller(
//            new Component_Instance(),
//            $user,
//            $sitePage
//        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
