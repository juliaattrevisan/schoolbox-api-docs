<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SubmissionFileMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Role;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Module extends BaseModule
{
    protected $basename = 'submission';

    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'viewOverview', $basePath, "/overview/{box_id}");
        $this->addGetRoute($router, 'downloadOverview', $basePath, "/overview/{box_id}/csv");
        $this->addGetRoute($router, 'deleteFile', $basePath, "/delete-file/{box_id}/hash/{hash}");
    }

    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                '.+(?:Overview)',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the item to params, if box_id is in the route.

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                if (array_key_exists('box_id', $params)) {
                    $box = $this->getSubmissionBoxMapper()->findById($params['box_id']);

                    // yeah... sure... doing this manually isn't outrageous at all...
                    $box->setCategory($this->getFolderMapper()->findById($box->getCategory()->getId()));

                    return array(
                        'box' => $box,
                    );
                }
                return array();
            }
        );
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'submissionFileMapper' => $this->getMapperFactory()->getLazy(SubmissionFileMapper::class)
            ])
        );
    }



    /* Getters / Setters
     */

    /**
     * @return SubmissionBoxMapper
     */
    protected function getSubmissionBoxMapper()
    {
        return $this->getMapperFactory()->get(SubmissionBoxMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
