<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Intellischool;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Intellischool\JwtFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Factory for creating JWTs for sending to Intellischool.
     */
    private JwtFactory $jwtFactory;

    /**
     * Constructor
     *
     * @param JwtFactory $jwtFactory
     */
    public function __construct(JwtFactory $jwtFactory)
    {
        $this->jwtFactory = $jwtFactory;
    }

    /**
     * Display the admin console for Intellischool for admins to edit settings in Intellischool Analytics themselves
     *
     */
    public function getConsole()
    {
        $this->response = $this->responseFromTemplate(
            '@adminIntellischool/console.twig',
            [
                'pageCurrent'      => $this->routeGenerator->generate('adminIntellischool.getConsole'),
                'allBreadcrumbs'   => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminIntellischool.getConsole')),
                'ltiEndpoint' => $this->jwtFactory::LTI_ENDPOINT,
                'jwt'         => $this->jwtFactory->buildForAdminView($this->getUser()),
                'title' => 'Intellischool - Admin Console'
            ],
            'Intellischool - Admin Console'
        );
    }
}
