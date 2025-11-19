<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem\Guid;

use Alaress_Date_Format;
use Aura\Router\Generator;
use Aura\Router\Matcher;
use Aura\Router\RouterContainer;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemCollection;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemCollectionInterface;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemInterface;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use Schoolbox\LTI\LineItem;
use Schoolbox\Url\Uri;

/**
 * Repository for GUID-based line items.
 */
class Repository implements LineItemRepositoryInterface
{
    public function __construct(
        private string $guid,
        private LineItem\Mapper $lineItemMapper,
        private Generator $routeGenerator,
        private Matcher $routeMatcher,
    ) {
    }

    public function find(string $lineItemIdentifier): ?LineItemInterface
    {
//        // Line item identifier will be a URL: extract the ID and GUID from it
//        $url  = new Uri($lineItemIdentifier);
//        $path = $url->getPath();
//
//        $dummyRoute = $this->routeGenerator->generate(
//            'ltiPlatformLineItemGuid.getLineItemForGuidAndLineItem',
//            [
//                'id'   => 0,
//                'guid' => 'xxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
//            ],
//        );
//
//        // Map this to a route
//        $url->getPath();
//        $assessmentId = $this->ltiAssessment->getId();
//        $route        = $this->routeContainer->getMatcher()->match($url->getPath());
//            'ltiPlatformLineItemGuid.getLineItemForGuidAndLineItem',
//            ['id' => ],
//        );
        // TODO: Implement find() method.
        return null;
    }

    public function findCollection(
        ?string $resourceIdentifier = null,
        ?string $resourceLinkIdentifier = null,
        ?string $tag = null,
        ?int $limit = null,
        ?int $offset = null
    ): LineItemCollectionInterface {
        // TODO: Implement findCollection() method.
        return new LineItemCollection([]);
    }

    public function save(LineItemInterface $lineItem): LineItemInterface
    {
        $lineItemModel = $this->lineItemMapper->createModel()
            ->setLtiAssessmentGuid($this->guid)
            ->setScoreMaximum($lineItem->getScoreMaximum())
            ->setLabel($lineItem->getLabel())
            ->setResourceId($lineItem->getResourceIdentifier())
            ->setResourceLinkId($lineItem->getResourceLinkIdentifier())
            ->setTag($lineItem->getTag())
            ->setStartDate(
                $lineItem->getStartDateTime()
                    ? Alaress_Date_Format::newFromTimestamp($lineItem->getStartDateTime()->getTimestamp())
                    : null
            )
            ->setEndDate(
                $lineItem->getEndDateTime()
                    ? Alaress_Date_Format::newFromTimestamp($lineItem->getEndDateTime()->getTimestamp())
                    : null
            );

        // Unsure what to do with:
        // identifier
        // custom properties
        // submission review
        $this->lineItemMapper->save($lineItemModel);

        // Now the line item has an ID, its identifier can be set?
        return $lineItem->setIdentifier(
            $this->routeGenerator->generate(
                'ltiPlatformLineItemGuid.getLineItemForGuidAndLineItem',
                [
                    'id'   => $lineItemModel->getId(),
                    'guid' => $lineItemModel->getLtiAssessmentGuid(),
                ],
            )
        );
    }

    public function delete(string $lineItemIdentifier): void
    {
        // TODO: Implement delete() method.
    }
}
