<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress\Authorizer\Rule\Callback;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Ramsey\Uuid\Uuid;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\LineItem as LtiAssessmentLineItem;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiAssessmentScoreMapper;

/**
 * LTI Platform sub-module for line items identified by a GUID.
 *
 * For line items identified by a GUID: that GUID corresponds to a row in
 * `lti_line_item`.
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
        // Line items container routes
        $this->register(
            self::METHOD_GET,
            '/{guid}',
            'getLineItemsForGuid',
            null,
            $this->assertValidGuid(...),
        );
        $this->register(
            self::METHOD_POST,
            '/{guid}',
            'postLineItemForGuid',
            null,
            $this->assertValidGuid(...),
        );

        // Line item routes
        $this->register(
            self::METHOD_GET,
            '/{guid}/{id}',
            'getLineItemForGuidAndLineItem',
            null,
            $this->preloadLineItem(...),
        );
        $this->register(
            self::METHOD_PUT,
            '/{guid}/{id}',
            'putLineItemForGuidAndLineItem',
            null,
            $this->preloadLineItem(...),
        );
        $this->register(
            self::METHOD_DELETE,
            '/{guid}/{id}',
            'deleteLineItemForGuidAndLineItem',
            null,
            $this->preloadLineItem(...),
        );

        // Score publish route
        $this->register(
            self::METHOD_POST,
            '/{guid}/{id}/scores',
            'postScorePublishForGuidAndLineItem',
            null,
            $this->preloadLineItem(...),
        );
    }

    /**
     * Preload which asserts the given GUID is actually a valid GUID value.
     *
     * @param array $params
     * @return array
     */
    private function assertValidGuid(array $params): array
    {
        if (!Uuid::isValid($params['guid'] ?? '')) {
            throw new NotFound();
        }
        return [];
    }

    /**
     * Preload the line item for the given ID, also validating that its GUID
     * is the same as the one in the route.
     *
     * @param array $params
     * @return array
     */
    private function preloadLineItem(array $params): array
    {
        $lineItemMapper = $this->getMapperFactory()->get(LtiAssessmentLineItem\Mapper::class);
        $lineItem       = $lineItemMapper->findById($params['id']);
        if ($lineItem->getLtiAssessmentGuid() !== ($params['guid'] ?? '')) {
            throw new NotFound();
        }
        return [
            'lineItem' => $lineItem,
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(LtiAssessmentLineItem\Mapper::class),
            $this->getMapperFactory()->get(LtiAssessmentMapper::class),
            $this->getMapperFactory()->get(LtiAssessmentScoreMapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->injector->get('lti.registrations'),
            $this->injector->get('lti.userCodec'),
            $this->injector->get('lti.accessTokenCache'),
            $this->injector->get('lti.logger'),
        );
    }
}
