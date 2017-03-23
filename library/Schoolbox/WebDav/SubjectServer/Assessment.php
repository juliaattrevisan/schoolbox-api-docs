<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Alaress_Date_Format;
use Sabre\DAV;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Resource\Storage\Model as StorageModel;
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
     * @var StorageDataSource
     */
    private $storageDataSource;

    /**
     * @var DueWorkResponseDataSource
     */
    private $dueWorkResponseDataSource;

    /**
     * Constructor
     *
     * @param User        $user
     * @param IAssessable $assessment
     */
    public function __construct(
        User $user,
        IAssessable $assessment,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource
    ) {
        $this->user                      = $user;
        $this->assessment                = $assessment;
        $this->storageDataSource         = $storageDataSource;
        $this->dueWorkResponseDataSource = $dueWorkResponseDataSource;
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

    /**
     * Uploads a student submission to the assessment.
     *
     * @throws DAV\Exception\Forbidden if the file cannot be uploaded
     *
     * @param string          $name
     * @param string|resource $data
     *
     * @return null ETags aren't being returned for now.
     */
    public function createFile($name, $data = null)
    {
        // TODO this ought to use the same API as saving a file thru the controller
        if ($this->assessment instanceof DueWork) {
            $participant = $this->assessment->getParticipantIfExtant($this->user);
            if ($participant instanceof Participant) {
                $allResponses = $participant->getAllResponses();

                // This could error out, that's intentional
                $this->assessment->assertAcceptSubmission($allResponses);

                // Create a new response
                $response = new DueWorkResponse();
                $response
                    ->setAssessable($this->assessment)
                    ->setSubmittedBy($this->user)
                    ->setSubmittedOnBehalfOf($this->user)
                    ->setCreatedAt(Alaress_Date_Format::now())
                    ->setUpdatedAt(Alaress_Date_Format::now())
                    ->setAllFiles(new GenericCollection(StorageModel::class))
                    // Not a draft
                    ->setSubmittedAt(Alaress_Date_Format::now());

                // Add a single file
                $storage = $this->storageDataSource->acceptStream($name, $data, false);
                $response->getAllFiles()->add($storage);

                // Save
                $this->dueWorkResponseDataSource->save($response);
            }
        }
        return null;
    }

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
