<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Alaress\Specification\Base;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\IAssessable;
use TypeError;
use UnexpectedValueException;

/**
 * Spec pattern for validating that the change between two rubric row sets is
 * valid.
 */
class ValidRowSetDeletion extends Base
{
    /**
     * Translator, used for translating error messages
     *
     * @var RosettaStone
     */
    private $translator;


    /**
     * Rowset already in use
     *
     * If null, no rowset exists
     *
     * @var RowSet|null
     */
    private $existingRowSet;


    /**
     * Are capabilities or indicators in use allowed to be modified?
     *
     * @var bool
     */
    private $allowModifyUsed;


    /**
     * If set, exclude this assessment from checks on whether a capability or
     * indicator is in use.
     *
     * Could be used when modifying a row set on a due work page: capabilities
     * and indicators may be modified if the due work being modified is the
     * only assessment using the cap/ind.
     *
     * @var IAssessable|null
     */
    private $allowModifyExclusion;


    /**
     * Constructor
     *
     * @param RosettaStone     $translator
     * @param RowSet|null      $existingRowSet
     * @param bool             $allowModifyUsed
     * @param IAssessable|null $allowModifyExclusion
     */
    public function __construct(
        RosettaStone $translator,
        ?RowSet $existingRowSet,
        bool $allowModifyUsed,
        ?IAssessable $allowModifyExclusion
    ) {
        $this->translator           = $translator;
        $this->existingRowSet       = $existingRowSet;
        $this->allowModifyUsed      = $allowModifyUsed;
        $this->allowModifyExclusion = $allowModifyExclusion;
    }


    /**
     * `$candidate` satisfies this pattern if:
     * * used capabilities/indicators are allowed to be modified
     *   (`$this->allowModifyUsed` is true)
     * * no capability or indicator that is used by an assessment (which isn't
     *   `$this->allowModifyExclusion`) is removed
     *
     * TODO consider adding name change checking here: it's currently handled in persistRowSet
     *
     * @param RowSet $candidate
     * @return bool always true if spec is satisfied
     * @throws TypeError                if $candidate isn't a RowSet
     * @throws UnexpectedValueException if it isn't satisfied
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (!$candidate instanceof RowSet) {
            throw new TypeError('$candidate must be a ' . RowSet::class);
        }


        // Can I modify used capabilities and indicators?
        // Short-circuit everything
        if ($this->allowModifyUsed) {
            return true;
        }


        // No existing rowset? nothing can have been deleted from it
        if (!$this->existingRowSet) {
            return true;
        }


        // Figure out which capabilities and indicators have been removed
        $newCapabilityIds      = $candidate->retrieveCapabilityIds();
        $newIndicatorIds       = $candidate->retrieveIndicatorIds();
        $existingCapabilityIds = $this->existingRowSet->retrieveCapabilityIds();
        $existingIndicatorIds  = $this->existingRowSet->retrieveIndicatorIds();
        $deletedCapabilityIds  = array_diff($existingCapabilityIds, $newCapabilityIds);
        $deletedIndicatorIds   = array_diff($existingIndicatorIds, $newIndicatorIds);


        // Validate deleted capabilities aren't in use
        foreach ($deletedCapabilityIds as $deletedCapabilityId) {
            $existingCapability = $this->existingRowSet->retrieveCapability($deletedCapabilityId);
            if ($existingCapability && $existingCapability->isInUse($this->allowModifyExclusion)) {
                $errorMessage = sprintf(
                    "Cannot delete %s '%s' because it is in use elsewhere",
                    $this->translator->translate('rubricGroup'),
                    $existingCapability->getName()
                );
                throw new UnexpectedValueException($errorMessage);
            }
        }


        // Validate deleted indicators aren't in use
        foreach ($deletedIndicatorIds as $deletedIndicatorId) {
            $existingIndicator = $this->existingRowSet->retrieveIndicator($deletedIndicatorId);
            if ($existingIndicator && $existingIndicator->isInUse($this->allowModifyExclusion)) {
                $errorMessage = sprintf(
                    "Cannot delete %s '%s' because it is in use elsewhere",
                    $this->translator->translate('rubricCriterion'),
                    $existingIndicator->getName()
                );
                throw new UnexpectedValueException($errorMessage);
            }
        }


        // After all that:
        return true;
    }
}
