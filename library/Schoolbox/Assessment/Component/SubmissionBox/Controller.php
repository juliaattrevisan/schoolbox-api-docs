<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SubmissionFileMapper;

/**
 * FWIW, every protected method in here probably belongs in some other class, since they're all data manipulation
 *
 *
 * Class Controller
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox
 */
class Controller extends BaseController
{
    /** @var SubmissionFileMapper */
    private $submissionFileMapper;

    /**
     * Controller constructor.
     * @param SubmissionFileMapper $submissionFileMapper
     */
    public function __construct(SubmissionFileMapper $submissionFileMapper)
    {
        $this->submissionFileMapper = $submissionFileMapper;
    }

    /**
     * View the marks overview for the given box
     *
     * @api   /submission/overview/{box_id}
     * @level 150
     *
     * @param SubmissionBox $box
     * @deprecated 165 Ported to IAssessable route
     * @see \Schoolbox\Learning\Assessment\DueWork\Controller::getOverview()
     */
    public function viewOverview(SubmissionBox $box)
    {
        $data = ['assessmentId' => $box->getAssessmentModel()->getId()];
        $location = $this->getRouter()->generate('learningAssessment.getOverview', $data);
        $this->getResponse()->redirect->to($location);
    }


    /**
     * Download the CSV of the marks overview for the given box
     *
     * @api   /submission/overview/{box_id}/csv
     * @level 150
     *
     * @param SubmissionBox $box
     * @deprecated 165 Ported to IAssessable route
     * @see \Schoolbox\Learning\Assessment\DueWork\Controller::downloadOverview()
     */
    public function downloadOverview(SubmissionBox $box)
    {
        $data = ['assessmentId' => $box->getAssessmentModel()->getId()];
        $location = $this->getRouter()->generate('learningAssessment.downloadOverview', $data);
        $this->getResponse()->redirect->to("$location?format=csv");
    }

    /**
     * Delete a file from a submission box
     * @param SubmissionBox $box
     * @param string $hash
     */
    public function deleteFile(SubmissionBox $box, $hash)
    {
        $this->submissionFileMapper->deleteByColumnList([
            'student_submission_id' => $box->getId(),
            'hash' => $hash
        ]);
    }
}
