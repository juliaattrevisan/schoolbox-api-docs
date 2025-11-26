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
        $validGuidPermission     = new Callback($this->assertValidGuid(...));
        $validLineItemPermission = new Callback($this->assertGuidMatchesLineItemId(...));
        $lineItemPreload         = $this->preloadLineItem(...);

        // Line items container routes
        $this->register(
            self::METHOD_GET,
            '/{guid}',
            'getLineItemsForGuid',
            $validGuidPermission,
        );
        $this->register(
            self::METHOD_POST,
            '/{guid}',
            'postLineItemForGuid',
            $validGuidPermission,
        );

        // Line item routes
        $this->register(
            self::METHOD_GET,
            '/{guid}/{id}',
            'getLineItemForGuidAndLineItem',
            $validLineItemPermission,
            $lineItemPreload,
        );
        $this->register(
            self::METHOD_PUT,
            '/{guid}/{id}',
            'putLineItemForGuidAndLineItem',
            $validLineItemPermission,
            $lineItemPreload,
        );
        $this->register(
            self::METHOD_DELETE,
            '/{guid}/{id}',
            'deleteLineItemForGuidAndLineItem',
            $validLineItemPermission,
            $lineItemPreload,
        );

        // Score publish route
        $this->register(
            self::METHOD_POST,
            '/{guid}/{id}/scores',
            'postScorePublishForGuidAndLineItem',
            $validLineItemPermission,
            $lineItemPreload,
        );
    }

    /**
     * Preload the line item for the given ID.
     *
     * @param array $params
     * @return array
     */
    private function preloadLineItem(array $params): array
    {
        $lineItemMapper = $this->getMapperFactory()->get(LtiAssessmentLineItem\Mapper::class);
        return [
            'lineItem' => $lineItemMapper->findById($params['id']),
        ];
    }

    /**
     * Ensure the given GUID is actually a valid GUID value.
     *
     * @param array $params
     * @return void
     */
    private function assertValidGuid(array $params): void
    {
        if (!Uuid::isValid($params['guid'] ?? '')) {
            throw new NotFound();
        }
    }

    /**
     * Ensure the GUID in the path matches the GUID of the line item in the
     * path.
     *
     * Assumes that the GUID is valid, as it must have been in order to have
     * been created.
     *
     * @param array $params
     * @return void
     */
    private function assertGuidMatchesLineItemId(array $params): void
    {
        if (!$params['lineItem'] instanceof LtiAssessmentLineItem\Model) {
            throw new NotFound();
        }
        if ($params['lineItem']->getLtiAssessmentGuid() !== $params['guid']) {
            throw new NotFound();
        }
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
        );
    }
}
