<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\File;

use Alaress\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Authorizer\Rule;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile;
use Schoolbox\Mapper;

class Module extends BaseModule\Module
{

    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        parent::__construct($injector, $basename ?: 'learningDueWorkFeedbackFile');
    }


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws Mapper\Exception if no instance of model is found
     * @throws Mapper\Exception\Deleted if the model was found but has been deleted
     * @throws \InvalidArgumentException If mapper class not found
     */
    protected function registerRoutes()
    {
        $canDeleteFeedback = new Rule\IsSuperuser();

        $preloadFile = function ($params) {
            return [
                'file' => $this->getMapper()->findById($params['fileId']),
            ];
        };
        $this->register('GET', '/file/{fileId:\d+}/delete', 'deleteFile', $canDeleteFeedback, $preloadFile);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @return Mapper|SingleFile\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getMapper()
    {
        return $this->getMapperFactory()->get(SingleFile\Mapper::class);
    }
}
