<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiAssessmentScoreMapper;

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
    protected $basename = 'ltiPlatformLineItemGuid';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $this->register(
            self::METHOD_GET,
            '/{guid}',
            'getLineItemsForGuid',
        );

        $this->register(
            self::METHOD_POST,
            '/{guid}',
            'postLineItemForGuid',
        );

        $this->register(
            self::METHOD_POST,
            '/{guid}/scores',
            'postScorePublishForGuid',
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
