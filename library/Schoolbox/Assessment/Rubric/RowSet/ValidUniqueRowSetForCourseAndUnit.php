<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Alaress\Specification\Base;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use TypeError;
use UnexpectedValueException;

/**
 * Spec pattern for validating rubric row sets.
 *
 * Only applies to row sets for a course specification: this validates that no
 * capabilities within the row set have a name which conflicts with the name of
 * a capability within any units of the course.
 */
class ValidUniqueRowSetForCourseAndUnit extends Base
{
    /**
     * Translator, used for translating error messages
     *
     * @var RosettaStone
     */
    private $translator;


    /**
     * @var UnitSpecificationMapper
     */
    private $unitSpecMapper;


    /**
     * @var RubricDataSource
     */
    private $rubricMapper;


    /**
     * Constructor
     *
     * @param RosettaStone            $translator
     * @param UnitSpecificationMapper $unitSpecMapper
     * @param RubricDataSource        $rubricMapper
     */
    public function __construct(
        RosettaStone $translator,
        UnitSpecificationMapper $unitSpecMapper,
        RubricDataSource $rubricMapper
    ) {
        $this->translator     = $translator;
        $this->unitSpecMapper = $unitSpecMapper;
        $this->rubricMapper   = $rubricMapper;
    }


    /**
     * `$candidate` satisfies this pattern if:
     * * none of the rowsets its units use contain a matching capability
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


        // Not a course folder? just pass
        if (!$candidate->getContext()->getBaseContext()->getFolder()->hasCourseSpecification()) {
            return true;
        }


        // What units exist in this course?
        $unitsForCourse = $this->unitSpecMapper->findAllByCourseFolderId(
            $candidate->getContext()->getBaseContext()->getFolder()->getId()
        );


        // Get just the names of the capabilities we want to dedupe
        $courseCapabilityNames = [];
        foreach ($candidate->getCapabilities() as $capability) {
            $courseCapabilityNames[$this->normalizeForComparison($capability->getName())] = true;
        }


        // Get the capabilities for each unit in this course
        $duplicateCapabilities = [];
        foreach ($unitsForCourse as $unit) {
            $rowSetForUnit = $this->rubricMapper->retrieveRowSet($unit->getFolder(), false);

            foreach ($rowSetForUnit->getCapabilities() as $unitCapability) {
                // Is this unit capability name in the list of course capability names?
                if (isset($courseCapabilityNames[$this->normalizeForComparison($unitCapability->getName())])) {
                    // Add it to our list of culprits
                    $duplicateCapabilities[$unitCapability->getName()][] = [
                        'unitName' => $unit->getFolder()->getName(),
                        'folderId' => $unit->getFolder()->getId(),
                    ];
                }
            }
        }


        // If anything is in here, we have an error state
        if (!empty($duplicateCapabilities)) {
            $errorMessage = '';
            foreach ($duplicateCapabilities as $capabilityName => $units) {
                $errorMessage .= sprintf(
                    "<p>%s '%s' cannot be saved because %s %s with the same name already exists in the following unit(s) in this course:<br/><ul>",
                    \Scrub::text($this->translator->translate('rubricGroup', false, true)),
                    \Scrub::text($capabilityName),
                    \Scrub::text($this->translator->indefiniteArticle('rubricGroup')),
                    \Scrub::text($this->translator->translate('rubricCriterion'))
                );

                foreach ($units as $unit) {
                    $unitName = $unit['unitName'];
                    $folderId = $unit['folderId'];
                    $errorMessage .= sprintf(
                        "<li><a href='/rubric/manager/%s'>%s</a></li>",
                        \Scrub::text($folderId),
                        \Scrub::text($unitName)
                    );
                }
                $errorMessage .= '</ul></p>';
            }
            throw new UnexpectedValueException($errorMessage);
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
