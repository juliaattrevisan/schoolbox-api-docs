<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy\Mark;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\DueWork\StateMapper;

class NormalisedMark implements IStrategy
{
    /**
     * The columns that are expected in this csv format
     */
    private const array EXPECTED_COLUMNS = [
        'Class Code',
        'Student External ID',
        'Normalised Mark',
        'Comment',
        'Publish Date'
    ];


    /**
     * Constructor.
     *
     * @param StateMapper $stateMapper
     * @param string $column
     */
    public function __construct(private StateMapper $stateMapper, private string $column)
    {
    }


    /**
     * Calculate the (normalised mark, learner human readable mark, instructor human readable mark) tuple
     * via the dominant discriminator column $column
     *
     * @param array $row
     * @param IAssessable $assessment
     * @return array
     */
    public function calculate(array $row, IAssessable $assessment): array
    {
        /** Get mark schemes
         * - Instructor mark scheme is used to normalise what was inputted by the user
         * - Learner mark scheme is used when displaying the mark back to learners/parents
         */
        $instructorScheme = $assessment->getInstructorMarkScheme();
        $learnerScheme = $assessment->getMarkScheme();

        $normalisedMark = (float)trim((string)$row[$this->column]);

        /* This is what the student would see
         */
        $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);

        /* This is what the instructor would see, due to SA-18452
         * Disgustingly typehint this getInstructorMarkScheme() method which is unique to two activities atm
         */
        /** @var \Schoolbox\Learning\Assessment\DueWork\Model|\Schoolbox\Learning\Assessment\Reporting\Model $assessment */
        $instructorHumanReadableMark = $instructorScheme->getHumanReadableMark($normalisedMark);

        /* Add a really specific error circumstance where we calculated something illegal
         */
        if ($normalisedMark < 0 || $normalisedMark > 100) {
            throw new \RuntimeException('Invalid normalised mark calculated.');
        }

        /* Apparently this is how you detect a Submission State: an exception is thrown
         * trying to interpret it as one of the Mark Schemes. No wonder it was classified
         * as the $errors var. Wild!
         *
         * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
         */
        try {
            /* Find out what the learner's version would have been and save that too
             * Arguably you could skip this derivation, only doing it if $instructor scheme was different to $learner scheme
             */
            $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);
        } catch (\InvalidArgumentException $e) {
            if ('' !== $instructorHumanReadableMark) {
                /* If it's a Submission State, save the text as the human readable mark
                 * and derive its normalised mark from the uh Lists in admin
                 *
                 * If this fails, presume it's a freeform text and just save a human readable mark only
                 */
                try {
                    $code = $this->stateMapper->findOneByColumn('display_as', $instructorHumanReadableMark);

                    $normalisedMark = $code->getNormaliseAs();

                    /* There isn't a learner-specific Submission State: it's the same for both parties.
                     */
                    $learnerHumanReadableMark = $instructorHumanReadableMark;
                } catch (\Schoolbox\Mapper\Exception $e) {
                    /* Freeform text; learners see the same thing as instructors and there's no normalised mark
                     */
                    $normalisedMark = null;
                    $learnerHumanReadableMark = $instructorHumanReadableMark;
                }
            }
        }

        /* Compile all three data points into a tuple that processRow can use
         */
        return [
            'normalisedMark' => $normalisedMark,
            'learnerHumanReadableMark' => $learnerHumanReadableMark,
            'instructorHumanReadableMark' => $instructorHumanReadableMark,
        ];
    }

    /* Getters/Setters
     */

    /**
     * These columns are all we care about in this csv file
     *
     * @return string[]
     */
    public function getExpectedColumns(): array
    {
        return self::EXPECTED_COLUMNS;
    }
}
