<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\LineItem;

use Aura\Router\Generator;
use BadMethodCallException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Uri;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItem;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemCollectionInterface;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemInterface;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\User;

/**
 * A line item repository which stores only a single LTI assessment.
 */
class SingleAssessmentRepository implements LineItemRepositoryInterface
{
    /**
     * Constructor
     *
     * @param LtiAssessment   $ltiAssessment        The LTI assessment in this repository.
     * @param MapperProxy     $assessmentDataSource Data source for assessment objects: used for saving
     *                                              `$ltiAssessment`.
     * @param Generator       $routeGenerator       Router, used to generate the line item route for the assessment.
     * @param LoggerInterface $logger               Logger for what this repository does.
     */
    public function __construct(
        private LtiAssessment $ltiAssessment,
        private MapperProxy $assessmentDataSource,
        private Generator $routeGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Find the line item by its identifier, in this case the LTI assessment's
     * ID number.
     *
     * @param string $lineItemIdentifier
     * @return LineItemInterface|null
     */
    public function find(string $lineItemIdentifier): ?LineItemInterface
    {
        // TODO consider whether identifier should be in the object, like
        //      lti_line_item.identifier_url
        // Line item identifier: the line item request URI
        $url          = new Uri($lineItemIdentifier);
        $assessmentId = $this->ltiAssessment->getId();
        $route        = $this->routeGenerator->generate(
            'ltiPlatformLineItem.getLineItemForAssessment',
            ['assessmentId' => $assessmentId],
        );
        if ($url->getPath() === $route) {
            $this->logger->debug(
                __METHOD__ . ': line item found',
                $this->generateLogContext($lineItemIdentifier),
            );
            return new LineItem(
                $this->ltiAssessment->getLineItemScoreMaximum() ?? 0,
                $this->ltiAssessment->getTitle(),
                $lineItemIdentifier,
                $this->ltiAssessment->getLineItemResourceId(),
                null,
                null,
                $this->ltiAssessment->getOpenAt()?->getDateTimeObject(),
                $this->ltiAssessment->getCloseAt()?->getDateTimeObject(),
            );
        }
        $this->logger->debug(
            __METHOD__ . ': line item not found',
            $this->generateLogContext($lineItemIdentifier),
        );
        return null;
    }

    /**
     * This method is not implemented: it should be only necessary to use
     * `find()`.
     *
     * @throws BadMethodCallException
     */
    public function findCollection(
        ?string $resourceIdentifier = null,
        ?string $resourceLinkIdentifier = null,
        ?string $tag = null,
        ?int $limit = null,
        ?int $offset = null
    ): LineItemCollectionInterface {
        $this->logger->error(__METHOD__ . ': not implemented', $this->generateLogContext());
        throw new BadMethodCallException('findCollection() method not implemented');
    }

    /**
     * Update `$this->assessment` with the fields from `$lineItem`.
     *
     * @param LineItemInterface $lineItem
     * @return LineItemInterface
     * @throws MapperException if the item couldn't be saved
     */
    public function save(LineItemInterface $lineItem): LineItemInterface
    {
        $this->ltiAssessment
            ->setLineItemScoreMaximum($lineItem->getScoreMaximum())
            ->setTitle($lineItem->getLabel())
            ->setLineItemResourceId($lineItem->getResourceIdentifier());

        // Also consider whether the dates can be set here?

        try {
            // No user exists for this setting:
            // thus need to fall back to assessment mapper
            $this->assessmentDataSource->save($this->ltiAssessment, new User());
        } catch (MapperException $e) {
            $this->logger->error(
                __METHOD__ . ' error: line item not saved',
                $this->generateLogContext(),
            );
            throw $e;
        }
        $this->logger->debug(
            __METHOD__ . ': line item saved',
            $this->generateLogContext(),
        );
        return $lineItem;
    }

    /**
     * This method is not implemented: it's not possible to delete the LTI
     * assessment with this repository.
     *
     * @throws BadMethodCallException
     */
    public function delete(string $lineItemIdentifier): void
    {
        $this->logger->error(__METHOD__ . ': not implemented', $this->generateLogContext($lineItemIdentifier));
        throw new BadMethodCallException('delete() method not implemented');
    }

    /**
     * Generate a consistent context for log messages.
     *
     * @param string|null $lineItemIdentifier
     * @return array
     */
    private function generateLogContext(
        ?string $lineItemIdentifier = null,
    ): array {
        $context['assessmentId'] = $this->ltiAssessment->getId();
        if (isset($lineItemIdentifier)) {
            $context['identifier'] = $lineItemIdentifier;
        }
        return $context;
    }
}
