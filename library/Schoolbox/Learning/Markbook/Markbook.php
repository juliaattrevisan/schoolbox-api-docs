<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Class Markbook
 */
class Markbook
{
    /**
     * The assessments that are a part of this markbook.
     *
     * @var Collection|IAssessable[]
     */
    protected $assessments;

    /**
     * Statistics for each assessment.
     *
     * key:   assessment ID
     * value: an array of statistics:
     *        * average
     *        * sd
     *        * min
     *        * max
     *
     * @var array
     */
    protected $statistics = [];

    /**
     * Build a markbook out of a list of assessments.
     *
     * This assumes that all participants, responses and feedbacks have been filled out.
     *
     * @param Collection|IAssessable[] $assessments
     */
    public function __construct(Collection $assessments)
    {
        $this->assessments = $assessments;
        $this->collateStatistics();
    }

    /**
     * Get all assessments in this collection.
     *
     * @return Collection|IAssessable[]
     */
    public function getAssessments()
    {
        return $this->assessments;
    }

    /**
     * Retrieves all students who have participated in any of the assessments in the markbook.
     *
     * @return Collection|User[]
     */
    public function getStudents()
    {
        $participants = new Collection();
        foreach ($this->getAssessments() as $assessment) {
            foreach ($assessment->getAllParticipants() as $participant) {
                $student = $participant->getUser();
                $participants[$student->getId()] = $student;
            }
        }
        // Sort by name?
        return $participants;
    }

    /**
     * Gets all stats for this markbook.
     *
     * @return array
     */
    public function getStatistics()
    {
        return $this->statistics;
    }

    /**
     * Collates statistics for each assessment into an array.
     */
    protected function collateStatistics()
    {
        // Current stats:
        // average, SD, min/max
        foreach ($this->getAssessments() as $assessment) {

            // Collate marks for each assessment
            $assessmentMarks = [];
            $statistics      = [
                'average' => null,
                'sd'      => null,
                'min'     => null,
                'max'     => null,
                'count'   => 0,
            ];
            foreach ($assessment->getAllParticipants() as $participant) {
                // Use only most recent published feedback
                $feedback = $participant->findMostRecentPublishedFeedback();
                if (isset($feedback)) {
                    $normMark = $feedback->getNormalisedMark();
                    if (isset($normMark)) {
                        $assessmentMarks[] = $normMark;
                        $statistics['count']++;
                    }
                }
            }

            // Calculate stats for this assessment
            if ($statistics['count'] > 0) {
                $statistics['average'] = array_sum($assessmentMarks) / $statistics['count'];
                $statistics['min']     = min($assessmentMarks);
                $statistics['max']     = max($assessmentMarks);

                if ($statistics['count'] > 1) {
                    // Standard deviation only makes sense for a sample size of at least 2
                    $variance = 0;
                    foreach ($assessmentMarks as $mark) {
                        $variance += pow($mark - $statistics['average'], 2);
                    }
                    // Old markbook used population standard deviation, rather than sample
                    // To use sample SD: subtract 1 from denominator
                    $statistics['sd'] = sqrt($variance / $statistics['count']);
                }
            }

            $this->statistics[$assessment->getId()] = $statistics;
        }

        // Possible extra stats (for box plots):
        // median, Q1, Q3, lower and upper fence
    }
}
