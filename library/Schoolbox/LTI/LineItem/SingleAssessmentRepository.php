<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\LineItem;

use Aura\Router\Router;
use BadMethodCallException;
use Laminas\Uri\Uri;
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
     * The LTI assessment in this repository.
     */
    private LtiAssessment $ltiAssessment;

    /**
     * Router, used to generate the line item route for the assessment.
     */
    private Router $router;

    /**
     * Constructor
     *
     * @param LtiAssessment $ltiAssessment
     * @param Router        $router
     */
    public function __construct(LtiAssessment $ltiAssessment, Router $router)
    {
        $this->ltiAssessment = $ltiAssessment;
        $this->router        = $router;
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
        $route        = $this->router->generate(
            'ltiPlatformLineItem.getLineItemForAssessment',
            ['assessmentId' => $assessmentId],
        );
        if ($url->getPath() === $route) {
            return new LineItem(
                $this->ltiAssessment->getLineItemScoreMaximum() ?? 0,
                $this->ltiAssessment->getTitle(),
                $url->getPath(),
            );
        }
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
        throw new BadMethodCallException('delete() method not implemented');
    }
}
