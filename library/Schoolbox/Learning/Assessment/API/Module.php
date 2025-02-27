<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\API;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Resource\Storage;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'apiAssessment';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Only superusers can access the Groups API
         */
        $hasApiAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/{assessmentId}', 'apiAssessmentGet', $hasApiAccess);
        $this->register('GET', '', 'apiAssessmentSearch', $hasApiAccess);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $yearLevelMapper     = $this->getMapperFactory()->get(YearLevelMapper::class);
        $submissionMapper    = $this->getMapperFactory()->get(SubmissionMapper::class);
        $storageTranslator   = new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfig()));
        $apiAssessmentHelper = new Helper($yearLevelMapper, $submissionMapper, $storageTranslator);
        $validator           = new Validator();
        return new Controller($apiAssessmentHelper, $validator);
    }
}
