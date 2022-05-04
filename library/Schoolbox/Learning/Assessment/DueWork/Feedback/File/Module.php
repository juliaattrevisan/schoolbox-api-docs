<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\File;

use Alaress\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Authorizer\Rule;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile;
use Schoolbox\Mapper;

class Module extends BaseModule\Module
{
    /**
     * @var string
     */
    protected $basename = 'learningDueWorkFeedbackFile';

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
    public function buildController(): Base
    {
        return new Controller($this->getMapper());
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
