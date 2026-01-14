<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\LineItem;

use Alaress_Date_Format;
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
        // TODO consider whether identifier should be stored in a db column,
        //      akin to `lti_line_item.identifier_url`
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
            return $this->convertModelToLineItem($lineItemIdentifier);
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
        $this->ltiAssessment = $this->ltiAssessment
            ->setLineItemScoreMaximum($lineItem->getScoreMaximum())
            ->setTitle($lineItem->getLabel())
            ->setLineItemResourceId($lineItem->getResourceIdentifier());

        // Also set dates here, respecting ordering of dates which have no
        // parallel in LTI: publish <= open, due <= close.
        // Always set date values, even if they are null: as per
        // https://www.imsglobal.org/spec/lti-ags/v2p0#updating-a-line-item,
        // the passed line item will be saved as-is, unset fields will be set to
        // null.
        // Note that the assessment validator prevents assessments from being
        // saved if:
        // * their start date is before their end date
        // * there is no start or end date
        //   (and it's an implementation, which it will always be)
        $startTime = $lineItem->getStartDateTime()
            ? new Alaress_Date_Format($lineItem->getStartDateTime())
            : null;
        $endTime = $lineItem->getEndDateTime()
            ? new Alaress_Date_Format($lineItem->getEndDateTime())
            : null;
        $this->ltiAssessment = $this->ltiAssessment
            ->setOpenAt($startTime)
            ->setDueAt($endTime);
        if ($startTime && $this->ltiAssessment->getPublishAt()?->gt($startTime)) {
            $this->ltiAssessment = $this->ltiAssessment->setPublishAt(clone $startTime);
        }
        if ($endTime && $this->ltiAssessment->getCloseAt()?->lt($endTime)) {
            $this->ltiAssessment = $this->ltiAssessment->setCloseAt(clone $endTime);
        }

        try {
            // No user exists for this setting:
            // just use an empty user, because LTI assessments don't care what
            // user is used to save them.
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
        return $this->convertModelToLineItem($lineItem->getIdentifier());
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


    /**
     * Helper function for converting this repository's assessment to the LTI
     * library's format.
     *
     * @param string $lineItemIdentifier
     * @return LineItemInterface
     */
    private function convertModelToLineItem(string $lineItemIdentifier): LineItemInterface
    {
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
}
