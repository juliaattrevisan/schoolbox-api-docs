<?php
/**
 * A DAV collection representing a single assessment within a subject.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class DAV_SubjectServer_Assessment extends Sabre\DAV\SimpleCollection
{
    /**
     * @var SubmissionBox
     */
    protected $assessment;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param SubmissionBox $assessment
     *
     * @return $this
     */
    public function setAssessment($assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return SubmissionBox
     */
    public function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Loads this student's submissions (and later on, returns).
     *
     * @return $this
     */
    public function loadChildren()
    {
        $mapper        = new Submission\FileMapper();
        $nameGenerator = new DAV_NameGenerator();
        $files         = $mapper->findByColumnList(
            [
                'owner'  => $this->getUser()->getId(),
                'box_id' => $this->getAssessment()->getId(),
                'draft'  => false,
            ],
            [
                'order' => ['realname' => 'asc', 'id' => 'asc',]
            ]
        );
        foreach ($files as $file) {

            if (!$file instanceof Submission\File) {
                continue;
            }

            /** @var Storage\Model $storage */
            $storage = $file->getStorage();

            if ($storage->exists()) {
                $child = new DAV_SubjectServer_Submission(
                    $nameGenerator->getUniqueChildName(
                        $this,
                        $nameGenerator->sanitiseName($file->getFilename())
                    ),
                    $storage->readStream()
                );
                $child
                    ->setUser($this->getUser())
                    ->setSubmission($file);
                $this->addChild($child);
            }
        }
        return $this;
    }

    /**
     * Uploads a student submission to the assessment.
     *
     * @throws Sabre\DAV\Exception\Forbidden if the file cannot be uploaded
     *
     * @param string          $name
     * @param string|resource $data
     *
     * @return null ETags aren't being returned for now.
     */
    public function createFile($name, $data = null)
    {
        $storage = Storage\Mapper::getInstance()->acceptStream($name, $data, false);

        $late_timestamp = $this->getAssessment()->getDueDate()->getUnixTimestamp();
        $submit_date    = new DateTime('now');
        $is_late        = ($late_timestamp < $submit_date->getTimestamp());

        $submission = new Submission\File();
        $submission
            ->setSubmissionBox($this->getAssessment())
            ->setOwner($this->getUser())
            ->setFilename($name)
            ->setCreatedAt($submit_date)
            ->setLate($is_late)
            ->setStorage($storage);

        //
        // Reasons a student couldn't submit a file:
        // * They aren't in this subject
        // * The subject doesn't take submissions online
        // * The submission is late
        // * They've already submitted and it's not a multi-submission assess
        // All of these are covered in SubmissionMapper->checkUploadability
        $mapper = new Submission\FileMapper();
        try {
            SubmissionBoxMapper::getInstance()->checkUploadability($this->getAssessment(), $this->getUser());

        } catch (Schoolbox\Resource\Exception $e) {
            throw new Sabre\DAV\Exception\Forbidden($e->getMessage(), $e->getCode(), $e);

        }


        $mapper->save($submission);

        // Queue it for Plagscanning as well, if that's supported.
        if (Config::get('plagscan_api_key') && Config::get('plagscan_username')) {
            $plagdoc = Plagscan_DocumentMapper::getInstance()->createFromSubmission($submission);
            Plagscan_DocumentMapper::getInstance()->save($plagdoc);
        }

        return null;

//		$dav_file = new Sabre\DAV\SimpleFile(
//			$submission->getFilename(),
//			file_get_contents($submission->getFileLocation())
//		);
//		return $dav_file->getETag();
    }

    /**
     * Gets the time which this assessment was uploaded.
     *
     * @return int
     */
    public function getLastModified()
    {
        $submission = $this->getAssessment();
        return $submission->getOpenDate()->getUnixTimestamp();
    }
}
