<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;

/**
 * The new (165+) LMS uses DueWorkFeedback which IS A IFeedback
 * The old (160-) LMS used SubmissionReturns.
 *
 * This class accepts and provides DueWorkFeedback objects, and
 * wraps a traditional Mapper to interface with the database.
 */
class MapperProxy
{
    /**
     * @var SubmissionReturnMapper
     */
    private $submissionReturnMapper;

    public function __construct(SubmissionReturnMapper $submissionReturnMapper)
    {
        $this->submissionReturnMapper = $submissionReturnMapper;
    }
}