<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Alaress\Specification\Base;
use Schoolbox\L10n\RosettaStone;
use TypeError;
use UnexpectedValueException;

/**
 * Spec pattern for validating rubric row sets.
 *
 * Validates that no capabilities or indicators within the row set have
 * conflicting names.
 */
class ValidUniqueRowSetForFolder extends Base
{
    /**
     * Translator, used for translating error messages
     *
     * @var RosettaStone
     */
    private $translator;


    /**
     * Constructor
     *
     * @param RosettaStone $translator
     */
    public function __construct(RosettaStone $translator)
    {
        $this->translator = $translator;
    }


    /**
     * `$candidate` satisfies this pattern if:
     * * no capabilities with duplicate names exist
     * * no capabilities contain indicators with duplicate names
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


        $capabilityNamesInUse = [];
        foreach ($candidate->getCapabilities() as $capability) {
            $normalizedCapabilityName = $this->normalizeForComparison($capability->getName());
            if (isset($capabilityNamesInUse[$normalizedCapabilityName])) {
                // error
                $errorMessage = sprintf(
                    "%s '%s' cannot be saved because %s %s with the same name already exists in",
                    $this->translator->translate('rubricGroup', false, true),
                    $capability->getName(),
                    $this->translator->indefiniteArticle('rubricGroup'),
                    $this->translator->translate('rubricGroup')
                );

                // The actual error msg depends on where the rowset lives
                if ($candidate->getContext()->getBaseContext()->getFolder()->hasUnitSpecification()) {
                    $errorMessage .= " this unit, or in the course this unit belongs to";
                } else {
                    $errorMessage .= " this folder";
                }
                throw new UnexpectedValueException($errorMessage);
            }
            $capabilityNamesInUse[$normalizedCapabilityName] = true;


            // check indicators
            $indicatorNamesInUse = [];
            foreach ($capability->getIndicators() as $indicator) {
                $normalizedIndicatorName = $this->normalizeForComparison($indicator->getName());
                if (isset($indicatorNamesInUse[$normalizedIndicatorName])) {
                    // error
                    $errorMessage = sprintf(
                        "%s '%s' cannot be saved because %s %s with the same name already exists in the %s '%s'",
                        $this->translator->translate('rubricCriterion', false, true),
                        $indicator->getName(),
                        $this->translator->indefiniteArticle('rubricCriterion'),
                        $this->translator->translate('rubricCriterion'),
                        $this->translator->translate('rubricGroup'),
                        $capability->getName()
                    );
                    throw new UnexpectedValueException($errorMessage);
                }
                $indicatorNamesInUse[$normalizedIndicatorName] = true;
            }
        }

        return true;
    }


    /**
     * Normalizes a capability or indicator name for comparison with others.
     *
     * TODO this is copy-pasted: figure out how to replace it
     *
     * @param string $name
     * @return string
     */
    private function normalizeForComparison(string $name): string
    {
        return strtolower(preg_replace('`\s+`', ' ', trim($name)));
    }
}
