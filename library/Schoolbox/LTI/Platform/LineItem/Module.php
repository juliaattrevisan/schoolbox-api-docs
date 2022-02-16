<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiAssessmentScoreMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * LTI Platform sub-module for line items.
 *
 * The standard line item for LTI assessments is the assessment itself.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'ltiPlatformLineItem';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $preloadAssessment = function (array $params) {
            /** @var AssessmentDataSource $assessmentDataSource */
            $assessmentDataSource = $this->injector->get('assessmentDataSource');
            try {
                $assessment = $assessmentDataSource->findByIdForReview($params['assessmentId']);
            } catch (MapperException $e) {
                /** @var ErrorMapper $errorMapper */
                $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
                throw $errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
            return [
                'assessment' => $assessment,
            ];
        };

        $this->register(
            self::METHOD_GET,
            '/assessment/{assessmentId}',
            'getLineItemForAssessment',
            null,
            $preloadAssessment,
        );

        $this->register(
            self::METHOD_POST,
            '/assessment/{assessmentId}/scores',
            'postScorePublishForAssessment',
            null,
            $preloadAssessment,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(LtiAssessmentMapper::class),
            $this->getMapperFactory()->get(LtiAssessmentScoreMapper::class),
            $this->injector->get('lti.registrations'),
            $this->injector->get('lti.userCodec'),
            $this->injector->get('lti.accessTokenCache'),
        );
    }
}
