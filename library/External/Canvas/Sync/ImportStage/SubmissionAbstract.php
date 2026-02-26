<?php

/**
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database\Connector\IConnector;
use External\Canvas\Importer\CanvasDb;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Database;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Mapper;

/**
 * Responsible for importing Submissions.
 */
abstract class SubmissionAbstract
{
    use ModelSaveTrait;

    /**
     * canvasUserId => SB User
     * @var array
     */
    private array $canvasUserIdUserMap = [];

    /** @var User */
    private User $schoolboxAdmin;

    /**
     * @param CanvasDb           $canvasDb
     * @param IConnector         $schoolboxDb
     * @param Participant\Mapper $participantMapper
     * @param UserMapper         $userMapper
     * @param LoggerInterface    $log
     * @param MapperProxy        $assessmentDataSource
     */
    public function __construct(
        protected CanvasDb $canvasDb,
        protected IConnector $schoolboxDb,
        protected Participant\Mapper $participantMapper,
        protected UserMapper $userMapper,
        protected LoggerInterface $log,
        protected Assessment\MapperProxy $assessmentDataSource,
    ) {
    }

    abstract public function sync($canvasAssessment, $assessmentCanvasId);

    /**
     * Finds assessment impl from assessment spec
     *
     * @param int $assessmentSpecId
     * @return GenericCollection|IAssessable[]
     */
    protected function findAllImplementationsBySpecificationId(int $assessmentSpecId): array|GenericCollection
    {
        $query     = '
                SELECT implementation_assessment_id
                FROM assessment_specification_implementation
                WHERE specification_assessment_id = :specId';
        $statement = $this->schoolboxDb->executeQuery($query, ['specId' => $assessmentSpecId]);
        $ids       = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return $this->assessmentDataSource->findAllByIds($ids);
    }

    /**
     * @param IAssessable $assessmentImpl
     * @return IAssessable|null
     */
    protected function loadParticipants(IAssessable $assessmentImpl): ?IAssessable
    {
        if (!$assessmentImpl->getContext()) {
            // this seems to happen for past assessments in future term in canvas
            $this->log->warning("Failed to load participant: assessment {$assessmentImpl->getId()} does not have context.");
            return null;
        }
        $assessmentCollection = $this->assessmentDataSource->createCollection()
                                                           ->add($assessmentImpl);
        try {
            $result = $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection(collection: $assessmentCollection);
        } catch (\Exception $e) {
            $this->log->warning('Failed to load participant: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return null;
        }
        foreach ($result as $assessmentWithParticipants) {
            // should be only one
            return $assessmentWithParticipants;
        }
        $this->log->warning('Failed to load participant: loadParticipantsIntoAssessmentCollection did not return any assessment.');
        return null;
    }


    /**
     * @param Plan                                       $planModel
     * @param \Schoolbox\Learning\Assessment\Participant $participant
     * @param \Alaress_Date_Format                       $createdAt
     * @param array                                      $canvasSubmission
     * @return void
     * @throws Mapper\Exception
     */
    public function saveParticipant(Plan $planModel, \Schoolbox\Learning\Assessment\Participant $participant, \Alaress_Date_Format $createdAt, array $canvasSubmission): void
    {
        $excluded = $canvasSubmission['excused'] === 'excused_submission';
        if ($participant->getId() && ($participant->isExcluded() === $excluded)) {
            // no need to change
            return;
        }

        $plan             = (new Participant\Plan\Model())->setId($planModel->getId());
        $participantModel = $this->participantMapper->createModel()
                                                    ->setUser($participant->getUser())
                                                    ->setPlan($plan)
                                                    ->setCreatedAt($createdAt);
        if ($participant->getId()) {
            $participantModel->setId($participant->getId());
        }
        if ($excluded) {
            $participantModel->setExcludedAt($createdAt);
        }

        $this->saveModel($this->participantMapper, $participantModel, true, false);
    }

    /**
     * Find a SB user who matches Canvas user with given canvas user id
     * 1. attempt to match using Canvas sis_user_id and SB synergy_id
     * 2. if 1 failed, try matching Canvas unique_name and SB email address
     * 3. if 2 failed, return schoolbox admin
     *
     * @param int|null $canvasUserId
     * @return User
     * @throws Mapper\Exception
     */
    protected function findUserByCanvasUserId(?int $canvasUserId): User
    {
        if (key_exists($canvasUserId, $this->canvasUserIdUserMap)) {
            return $this->canvasUserIdUserMap[$canvasUserId];
        }

        $sql = "SELECT sis_user_id, unique_id as unique_name FROM pseudonyms
                 WHERE workflow_state = 'active'
                   AND user_id = :userId";

        if (!$this->canvasDb->isCanvasData2) {
            $sql = "SELECT sis_user_id, unique_name FROM pseudonym_dim
                 WHERE workflow_state = 'active'
                   AND user_id = :userId";
        }

        $statement = $this->canvasDb->getConnector()->executeQuery($sql, ['userId' => $canvasUserId]);

        if ($canvasUser = $statement->fetch()) {
            // Try sis user id
            if ($canvasUser['sis_user_id'] !== null) {
                try {
                    $this->canvasUserIdUserMap[$canvasUserId] = $this->userMapper->findByExternalId($canvasUser['sis_user_id']);
                    return $this->canvasUserIdUserMap[$canvasUserId];
                } catch (Mapper\Exception $e) {
                }
            }

            // Try email address
            try {
                $emailUsers = $this->userMapper->findByEmail($canvasUser['unique_name']);
                foreach ($emailUsers as $user) {
                    $this->canvasUserIdUserMap[$canvasUserId] = $user;
                    return $this->canvasUserIdUserMap[$canvasUserId];
                }
            } catch (Mapper\Exception $e) {
            }
        }

        // If failed to find, return admin
        if (!isset($this->schoolboxAdmin)) {
            $this->schoolboxAdmin = $this->userMapper->findById($this->getSchoolboxAdminUserId());
        }
        $this->canvasUserIdUserMap[$canvasUserId] = $this->schoolboxAdmin;
        return $this->canvasUserIdUserMap[$canvasUserId];
    }


    /**
     * Returns the Schoolbox Admin user's id
     *
     * @return int
     * @throws Database\Exception
     */
    protected function getSchoolboxAdminUserId(): int
    {
        $sql = "SELECT id FROM user WHERE username = :username AND deleted_at IS NULL";

        return (int)$this->schoolboxDb->executeQuery($sql, ['username' => User::USERNAME_SCHOOLBOX_ADMIN])
                                      ->fetchColumn();
    }

    /**
     * @param IAssessableDataModel $iAssessable
     * @param User                 $reviewer
     * @param User                 $reviewee
     * @param array                $canvasSubmission
     * @param GenericCollection    $existingFeedbacks
     * @param IMarkScheme          $markScheme
     * @param IMarkScheme|null     $instructorMarkScheme (only relevant for due work)
     * @param string               $feedbackTime
     * @return mixed
     */
    abstract public function syncFeedback(
        IAssessableDataModel $iAssessable,
        User $reviewer,
        User $reviewee,
        array $canvasSubmission,
        GenericCollection $existingFeedbacks,
        IMarkScheme $markScheme,
        ?IMarkScheme $instructorMarkScheme,
        string $feedbackTime
    ): mixed;

    /**
     * @param string $canvasTime
     * @return \Alaress_Date_Format
     */
    protected function convertTime(string $canvasTime): \Alaress_Date_Format
    {
        // time string from Canvas is coming in UTC
        $canvasTimeUTC = date_create("{$canvasTime} UTC");
        return \Alaress_Date_Format::newFromTimestamp($canvasTimeUTC->getTimestamp());
    }
}
