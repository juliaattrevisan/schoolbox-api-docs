<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Sabre\DAV;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

/**
 * A DAV collection representing a single assessment within a subject.
 */
class Assessment extends DAV\Collection
{
    /**
     * @var IAssessable
     */
    private $assessment;

    /**
     * @var User
     */
    private $user;

    /**
     * Constructor
     *
     * @param User        $user
     * @param IAssessable $assessment
     */
    public function __construct(User $user, IAssessable $assessment)
    {
        $this->user       = $user;
        $this->assessment = $assessment;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->assessment->getTitle();
    }

    /**
     * Loads this student's submissions (and later on, returns).
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        $children    = [];
        $participant = $this->assessment->getParticipantIfExtant($this->user);
        if ($participant instanceof Participant) {
            foreach ($participant->getAllResponses() as $response) {
                if ($response->isSubmitted()) {
                    $children[] = new Response($response);
                }
            }
        }
        return $children;
    }

//    /**
//     * Uploads a student submission to the assessment.
//     *
//     * @throws Sabre\DAV\Exception\Forbidden if the file cannot be uploaded
//     *
//     * @param string          $name
//     * @param string|resource $data
//     *
//     * @return null ETags aren't being returned for now.
//     */
//    public function createFile($name, $data = null)
//    {
//        $storage = Storage\Mapper::getInstance()->acceptStream($name, $data, false);
//
//        // Create a new submission
//        $submission = new Submission\Submission();
//        $submission
//            ->setSubmissionBox($this->getAssessment())
//            ->setOwner($this->getUser())
//            ->setSubmittedAt(Alaress_Date_Format::now());
//
//        // Put a single file in
//        $submissionFile = new Submission\SingleFile\Model();
//        $submissionFile
//            ->setFilename($storage->getFilename())
//            ->setCreatedAt(Alaress_Date_Format::now())
//            ->setStorage($storage);
//
//        //
//        // Reasons a student couldn't submit a file:
//        // * They aren't in this subject
//        // * The subject doesn't take submissions online
//        // * The submission is late
//        // * They've already submitted and it's not a multi-submission assess
//        // All of these are covered in SubmissionMapper->checkUploadability
//        $mapper = new Submission\SubmissionMapper();
//        try {
//            SubmissionBoxMapper::getInstance()->checkUploadability($this->getAssessment(), $this->getUser());
//
//        } catch (Schoolbox\Resource\Exception $e) {
//            throw new Sabre\DAV\Exception\Forbidden($e->getMessage(), $e->getCode(), $e);
//
//        }
//
//        $mapper->save($submission);
//        return null;
//    }

    /**
     * Gets the time which this assessment was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        return $this->assessment->getPublishAt()->getUnixTimeStamp();
    }
}
