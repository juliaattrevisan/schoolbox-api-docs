<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Assessable;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;

/**
 * The single source of truth for assessment participants.
 *
 * @deprecated \Schoolbox\Learning\Assessment\DataModel\MapperProxy is probably what you want.
 */
class DataSource
{
    /** @var ResponseDataSource */
    protected $responseDataSource;
    
    /** @var ParticipantDataSource */
    protected $participantDataSource;

    /** @var FeedbackDataSource */
    protected $feedbackDataSource;

    /** @var AssessmentMapperProxy */
    protected $assessmentMapperProxy;

    /** @var TermMapper */
    protected $termMapper;

    /** @var folderMapper */
    protected $folderMapper;

    /**
     * Constructor
     *
     * @param ResponseDataSource      $responseDataSource
     * @param ParticipantDataSource      $participantDataSource
     * @param FeedbackDataSource      $feedbackDataSource
     * @param TermMapper              $termMapper;
     * @param FolderMapper            $folderMapper;
     * @param AssessmentMapperProxy $assessmentMapperProxy
     */
    public function __construct(
        ResponseDataSource $responseDataSource,
        ParticipantDataSource $participantDataSource,
        FeedbackDataSource $feedbackDataSource,
        TermMapper $termMapper,
        FolderMapper $folderMapper,
        AssessmentMapperProxy $assessmentMapperProxy
    ) {
        $this->responseDataSource       = $responseDataSource;
        $this->participantDataSource    = $participantDataSource;
        $this->feedbackDataSource       = $feedbackDataSource;
        $this->termMapper               = $termMapper;
        $this->folderMapper             = $folderMapper;
        $this->assessmentMapperProxy    = $assessmentMapperProxy;
    }

    /**
     * Find all assessments in which the user is participating
     * @param User $user
     * @param Term|null $term
     * @return Collection
     */
    public function findForUser(User $user, Term $term = null)
    {
        // If no term was specified use the current term

        if ($term == null) {
            $term = $this->termMapper->findForToday();
        }

        // Retrieve all folders the user currently has access to

        $userFolders = $this->folderMapper->findForUser($user, true);

        // Retrieve all folders the user has ever submitted anything to

        $userSubmissionFolders = $this->folderMapper->findFoldersWithSubmissions($user, $term);

        // Merge them together into a unique collection

        $userFolders->mergeUnique($userSubmissionFolders);

        // Iterate the folders and create a collection of assessments to return

        $returnAssessments = new Collection();

        /** @var Folder $folderCurrent */
        foreach ($userFolders as $folderCurrent) {

            $assessments = $this->assessmentMapperProxy->findAllForFolderId($folderCurrent->getId());

            /** @var IAssessable $assessment */
            foreach ($assessments as $assessment) {

                // Only interested in assessments due in the specified term

                if ($term->contains($assessment->getDueAt())) {

                    // Grab requested participant with their feedback
                    $this->participantDataSource->findForAssessment($assessment, $user, false, true);

                    // Add to return array
                    $returnAssessments->add($assessment);
                }
            }
        }

        return $returnAssessments;
    }
}
