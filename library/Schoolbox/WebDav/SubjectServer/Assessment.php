<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav\SubjectServer;

use Alaress_Date_Format;
use Sabre\DAV;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as DueWorkResponseFile;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkResponseDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Storage\Mapper as StorageDataSource;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

/**
 * A DAV collection representing a single assessment within a subject.
 */
class Assessment extends DAV\Collection
{
    /**
     * @var string
     */
    private $name;

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
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * @var DAV\INode[]
     */
    private $children;

    /**
     * Constructor
     *
     * @param string                    $name
     * @param User                      $user
     * @param IAssessable               $assessment
     * @param StorageDataSource         $storageDataSource
     * @param DueWorkResponseDataSource $dueWorkResponseDataSource
     * @param ErrorMapper               $errorMapper
     * @param DAV\INode[]               $children
     */
    public function __construct(
        $name,
        User $user,
        IAssessable $assessment,
        StorageDataSource $storageDataSource,
        DueWorkResponseDataSource $dueWorkResponseDataSource,
        ErrorMapper $errorMapper,
        array $children = []
    ) {
        $this->name                      = $name;
        $this->user                      = $user;
        $this->assessment                = $assessment;
        $this->storageDataSource         = $storageDataSource;
        $this->dueWorkResponseDataSource = $dueWorkResponseDataSource;
        $this->errorMapper               = $errorMapper;
        $this->children                  = $children;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add a child
     *
     * @param DAV\INode $child
     * @return $this
     */
    public function addChild(DAV\INode $child)
    {
        $this->children[] = $child;
        return $this;
    }

    /**
     * Loads this student's submissions (and later on, returns).
     *
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        return $this->children;
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
                // This could error out, that's intentional
                try {
                    $this->assessment->assertAcceptSubmission($participant);
                } catch (\DomainException $e) {
                    throw new DAV\Exception\Forbidden(
                        $this->errorMapper->getMessageById($e->getCode()),
                        $e->getCode(),
                        $e
                    );
                }

                // Get whatever was uploaded.
                // A 1-byte file will be rejected completely:
                // this is a stub the iOS client creates which seems to be unnecessary.
                $storage = $this->storageDataSource->acceptStream($name, $data, false);
                if ($storage->getSize() <= 1) {
                    return null;
                }

                // Create a new response
                $response = (new DueWorkResponse())
                    ->setAssessable($this->assessment)
                    ->setSubmittedBy($this->user)
                    ->setSubmittedOnBehalfOf($this->user)
                    ->setCreatedAt(Alaress_Date_Format::now())
                    ->setUpdatedAt(Alaress_Date_Format::now())
                    ->setAllFiles(new GenericCollection(DueWorkResponseFile::class))
                    // Not a draft
                    ->setSubmittedAt(Alaress_Date_Format::now());

                // Add a single file
                $attachedFile = (new DueWorkResponseFile())
                    ->setStorage($storage)
                    ->setFilename($storage->getFilename())
                    ->setEditableInline(false);
                $response->getAllFiles()->add($attachedFile);

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
