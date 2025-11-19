<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\LTI\LineItem;

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
        );
        $this->register(
            self::METHOD_POST,
            '/{guid}',
            'postLineItemForGuid',
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
     * Preload the line item for the given ID.
     *
     * @param array $params
     * @return array
     */
    private function preloadLineItem(array $params): array
    {
        $lineItemMapper = $this->getMapperFactory()->get(LineItem\Mapper::class);
        return [
            'lineItem' => $lineItemMapper->findById($params['id']),
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(LineItem\Mapper::class),
            $this->injector->get('lti.registrations'),
            $this->injector->get('lti.userCodec'),
            $this->injector->get('lti.accessTokenCache'),
        );
    }
}
