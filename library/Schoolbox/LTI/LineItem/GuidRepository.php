<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\LineItem;

use Alaress_Date_Format;
use Aura\Router\Generator;
use OAT\Library\Lti1p3Ags\Model\LineItem as LtiLineItem;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemCollection;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemCollectionInterface;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemInterface;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\LTI\Assessment\LineItem as SchoolboxLineItem;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Repository for GUID-based line items.
 */
class GuidRepository implements LineItemRepositoryInterface
{
    /**
     * Constructor
     *
     * @param string                   $guid           The GUID for the assessment this line item belongs to.
     * @param SchoolboxLineItem\Mapper $lineItemMapper Line item mapper, for persisting and fetching line items.
     * @param Generator                $routeGenerator Route generator: used to create line item identifiers.
     * @param UriInterface             $baseUrl        Base URL used when building the line item's identifier.
     * @param LoggerInterface          $logger         Log what this repository does.
     */
    public function __construct(
        private string $guid,
        private SchoolboxLineItem\Mapper $lineItemMapper,
        private Generator $routeGenerator,
        private UriInterface $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Find a single line item.
     *
     * @param string $lineItemIdentifier
     * @return LineItemInterface|null
     */
    public function find(string $lineItemIdentifier): ?LineItemInterface
    {
        $lineItem = $this->lineItemMapper->findByColumnList([
            'lti_assessment_guid' => $this->guid,
            'identifier_url'      => $lineItemIdentifier,
        ])->first();
        if ($lineItem) {
            $this->logger->debug(
                __METHOD__ . ': line item found',
                [
                    'identifier' => $lineItemIdentifier,
                    'lineItemId' => $lineItem->getId(),
                ],
            );
            return $this->convertModelToLineItem($lineItem);
        }
        $this->logger->debug(
            __METHOD__ . ': line item not found',
            [
                'identifier' => $lineItemIdentifier,
            ],
        );
        return null;
    }

    /**
     * Find a collection of line items.
     *
     * @param string|null $resourceIdentifier
     * @param string|null $resourceLinkIdentifier
     * @param string|null $tag
     * @param int|null    $limit
     * @param int|null    $offset
     * @return LineItemCollectionInterface
     */
    public function findCollection(
        ?string $resourceIdentifier = null,
        ?string $resourceLinkIdentifier = null,
        ?string $tag = null,
        ?int $limit = null,
        ?int $offset = null
    ): LineItemCollectionInterface {
        $pb = $this->lineItemMapper->getPredicateBuilder()
            ->equals('lti_assessment_guid', $this->guid);
        if (isset($resourceIdentifier)) {
            $pb = $pb->equals('resource_id', $resourceIdentifier);
        }
        if (isset($resourceLinkIdentifier)) {
            $pb = $pb->equals('resource_link_id', $resourceLinkIdentifier);
        }
        if (isset($tag)) {
            $pb = $pb->equals('tag', $tag);
        }

        $options = [];
        if (isset($limit)) {
            $options['limit'] = $limit;
        }
        if (isset($offset)) {
            $options['offset'] = $offset;
        }

        $lineItemModels = $this->lineItemMapper->findByPredicates($pb, $options);
        if (count($lineItemModels) > 0) {
            $this->logger->debug(
                __METHOD__ . ': line items found',
                [
                    'count' => count($lineItemModels),
                ],
            );
        } else {
            $this->logger->debug(
                __METHOD__ . ': line items not found',
            );
        }
        return new LineItemCollection($lineItemModels->map($this->convertModelToLineItem(...))->getAll());
    }

    /**
     * Save this line item.
     *
     * @param LineItemInterface $lineItem
     * @return LineItemInterface
     * @throws MapperException if the item couldn't be saved
     */
    public function save(LineItemInterface $lineItem): LineItemInterface
    {
        // Does a line item with this identifier already exist?
        // If so modify that one: otherwise create a new one
        $identifier = $lineItem->getIdentifier();
        if ($identifier) {
            $lineItemModel = $this->lineItemMapper->findOneByColumn('identifier_url', $identifier);
        } else {
            $lineItemModel = $this->lineItemMapper->createModel()
                ->setLtiAssessmentGuid($this->guid);
        }
        $lineItemModel = $lineItemModel
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

        // Not yet sure what to do with:
        // * custom properties
        // * submission review
        try {
            $this->lineItemMapper->save($lineItemModel);
        } catch (MapperException $e) {
            $this->logger->error(
                __METHOD__ . ' error: line item not saved',
                [
                    'message' => $e->getMessage(),
                ],
            );
            throw $e;
        }
        $this->logger->debug(
            __METHOD__ . ': line item saved',
            [
                'lineItemId' => $lineItemModel->getId(),
            ],
        );

        // Now the line item has an ID, its identifier can be set
        // (if it didn't already have one)
        if (!$identifier) {
            $identifier    = $this->buildIdentifier($lineItemModel);
            $lineItem      = $lineItem->setIdentifier($identifier);
            $lineItemModel = $lineItemModel->setIdentifierUrl($identifier);
            try {
                $this->lineItemMapper->save($lineItemModel);
            } catch (MapperException $e) {
                $this->logger->error(
                    __METHOD__ . ' error: identifier not added',
                    [
                        'message'    => $e->getMessage(),
                        'lineItemId' => $lineItemModel->getId(),
                    ],
                );
                throw $e;
            }
            $this->logger->debug(
                __METHOD__ . ': added identifier',
                [
                    'lineItemId' => $lineItemModel->getId(),
                    'identifier' => $identifier,
                ],
            );
        }

        return $lineItem;
    }

    /**
     * Delete the line item which matches the given identifier.
     *
     * @param string $lineItemIdentifier
     * @return void
     */
    public function delete(string $lineItemIdentifier): void
    {
        try {
            $deleted = $this->lineItemMapper->deleteByColumnList([
                'lti_assessment_guid' => $this->guid,
                'identifier_url'      => $lineItemIdentifier,
            ]);
        } catch (MapperException $e) {
            $this->logger->error(
                __METHOD__ . ' error: line item not deleted',
                [
                    'message'    => $e->getMessage(),
                    'identifier' => $lineItemIdentifier,
                ],
            );
            throw $e;
        }
        $this->logger->debug(
            __METHOD__ . ': line item deleted',
            [
                'identifier' => $lineItemIdentifier,
                'count'      => $deleted,
            ],
        );
    }

    /**
     * Helper function for converting a line item model to the LTI library's
     * format.
     *
     * @param SchoolboxLineItem\Model $lineItemModel
     * @return LineItemInterface
     */
    private function convertModelToLineItem(SchoolboxLineItem\Model $lineItemModel): LineItemInterface
    {
        return new LtiLineItem\LineItem(
            $lineItemModel->getScoreMaximum(),
            $lineItemModel->getLabel(),
            $this->buildIdentifier($lineItemModel),
            $lineItemModel->getResourceId(),
            $lineItemModel->getResourceLinkId(),
            $lineItemModel->getTag(),
            $lineItemModel->getStartDate()?->getDateTimeObject(),
            $lineItemModel->getEndDate()?->getDateTimeObject(),
        );
    }

    /**
     * Build an identifier for this line item, based on its GUID and ID.
     *
     * @param SchoolboxLineItem\Model $lineItemModel
     * @return string
     * @noinspection PhpDocMissingThrowsInspection - Aura\Router\Exception\RouteNotFound is never thrown
     */
    private function buildIdentifier(SchoolboxLineItem\Model $lineItemModel): string
    {
        /** @noinspection PhpUnhandledExceptionInspection - Aura\Router\Exception\RouteNotFound is never thrown */
        return $this->baseUrl
            ->withPath(
                $this->routeGenerator->generate(
                    'ltiPlatformLineItemGuid.getLineItemForGuidAndLineItem',
                    [
                        'id'   => $lineItemModel->getId(),
                        'guid' => $lineItemModel->getLtiAssessmentGuid(),
                    ],
                )
            )
            ->__toString();
    }
}
