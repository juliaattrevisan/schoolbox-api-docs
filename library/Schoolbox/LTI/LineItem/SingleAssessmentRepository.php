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
use Schoolbox\Url\Uri;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItem;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemCollectionInterface;
use OAT\Library\Lti1p3Ags\Model\LineItem\LineItemInterface;
use OAT\Library\Lti1p3Ags\Repository\LineItemRepositoryInterface;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;

/**
 * A line item repository which stores only a single LTI assessment.
 */
class SingleAssessmentRepository implements LineItemRepositoryInterface
{
    /**
     * Constructor
     *
     * @param LtiAssessment   $ltiAssessment  The LTI assessment in this repository.
     * @param Generator       $routeGenerator Router, used to generate the line item route for the assessment.
     * @param LoggerInterface $logger         Logger for what this repository does.
     */
    public function __construct(
        private LtiAssessment $ltiAssessment,
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
                $url->getPath(),
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
     * This method is not implemented: it's not possible to save the LTI
     * assessment with this repository.
     *
     * @throws BadMethodCallException
     */
    public function save(LineItemInterface $lineItem): LineItemInterface
    {
        $this->logger->error(__METHOD__ . ': not implemented', $this->generateLogContext());
        throw new BadMethodCallException('save() method not implemented');
    }

    /**
     * This method is not implemented: it's not possible to delete the LTI
     * assessment with this repository.
     *
     * @throws BadMethodCallException
     */
    public function delete(string $lineItemIdentifier): void
    {
        $this->logger->error(__METHOD__ . ': not implemented', $this->generateLogContext());
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
