<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport;

use Alaress\Database\Predicate\InvalidColumnException;
use Custom\Tigs\Summative\StudentAttributes;
use External\CachingConnectorAbstract;
use External_ClassInterface;
use External_Synergetic_Class;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnderflowException;
use function array_merge;
use function count;
use function implode;


/**
 * Responsible for consistently deriving the set of teachers who teach/taught a subject.
 *
 * If(current)
 *   just fetch current teachers.
 * else(historical)
 *   if(using Synergetic)
 *     fetch historical teacher from SIS
 *   else
 *     fetch staff who gave feedback to assessments in folder
 */
class TeacherFetcher
{
    /** @var External_ClassInterface */
    private $externalClass;

    /** @var UserMapper */
    private $userMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /**
     * @param External_ClassInterface $externalClass
     * @param UserMapper              $userMapper
     * @param Assessment\Mapper       $assessmentMapper
     */
    public function __construct(
        External_ClassInterface $externalClass,
        UserMapper $userMapper,
        Assessment\Mapper $assessmentMapper
    ) {
        $this->externalClass    = $externalClass;
        $this->userMapper       = $userMapper;
        $this->assessmentMapper = $assessmentMapper;
    }


    /**
     * Returns the staff users who teach/taught the specified folder
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @return GenericCollection|User[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    public function getTeachers(Folder\Model $folder, StudentAttributes $attributes): GenericCollection
    {
        if ($attributes->isCurrent()) {
            return $this->getStaffUsers($this->externalClass->getTeachers($folder->getCodeString()));
        }

        // Bypass encapsulation to extract the underlying external class connection
        if ($this->externalClass instanceof CachingConnectorAbstract) {
            $externalClassInner = $this->externalClass->getDatabase();
            if ($externalClassInner instanceof External_Synergetic_Class) {
                return $this->getStaffUsers($externalClassInner->getTeachers(
                    $folder->getCodeString(),
                    $attributes->getReportDate()
                ));
            }
        }

        return $this->getStaffWhoGaveFeedback($folder, $attributes);
    }


    /**
     * Loads staff users from schoolbox DB who have the specified external ids
     *
     * @param string[] $externalIds
     * @return GenericCollection|User[]
     */
    private function getStaffUsers(array $externalIds): GenericCollection
    {
        if (!count($externalIds)) {
            return User::createCollection();
        }
        return $this->userMapper->findByColumnList([
            'synergy_id'   => $externalIds,
            'g.group_type' => Role::STAFF,
        ]);
    }


    /**
     * Returns the staff users who gave feedback to the specfied folder during the report's period
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @return GenericCollection|User[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getStaffWhoGaveFeedback(Folder\Model $folder, StudentAttributes $attributes): GenericCollection
    {
        // Load teachers who gave feedback
        $assessments = $this->findAssessmentsForFolderAndReport($folder, $attributes);
        if (!$assessments->count()) {
            return User::createCollection();
        }

        $userIds = $this->findUsersWhoGaveFeedback($assessments);
        if (!count($userIds)) {
            return User::createCollection();
        }

        $columnList = [
            $this->userMapper->getIdentifierColumn() => $userIds,    // Users who gave feedback, and
            'g.group_type'                           => Role::STAFF, // are staff
        ];
        return $this->userMapper->findByColumnList($columnList);
    }


    /**
     * Returns a set of assessments that belong to a folder and are due within the report's time period
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @return Collection
     * @throws InvalidColumnException
     */
    private function findAssessmentsForFolderAndReport(Folder\Model $folder, StudentAttributes $attributes): Collection
    {
        // Load all users who gave feedback to assessments due during report period
        $dueDatePb = $this->assessmentMapper->getPredicateBuilder()
                                            ->between(
                                                'due_date',
                                                $attributes->getCurriculumSemesterStart(),
                                                $attributes->getCurriculumSemesterFinish()
                                            );
        return $this->assessmentMapper->findAllForFolderId($folder->getId(), $dueDatePb);
    }


    /**
     * Returns the ids of users who gave feedback to the set of assessments
     *
     * @param Collection $assessments
     * @return int[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function findUsersWhoGaveFeedback(Collection $assessments): array
    {
        $assessmentIdProperty = $this->assessmentMapper->getIdentifierProperty();

        $taskPb = $this->assessmentMapper->getPredicateBuilder()
                                         ->in('t_a.id', $assessments->getPropertyValues($assessmentIdProperty))
                                         ->equals('tc.completion_status', Completion::STATUS_TEACHER_ASSESSED_COMPLETE);

        $quizPb = $this->assessmentMapper->getPredicateBuilder()
                                         ->in('q_a.id', $assessments->getPropertyValues($assessmentIdProperty));

        $subBoxPb = $this->assessmentMapper->getPredicateBuilder()
                                           ->in('sb_a.id', $assessments->getPropertyValues($assessmentIdProperty))
                                           ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

        $taskWhere   = implode(' AND ', $taskPb->getWhere());
        $quizWhere   = implode(' AND ', $quizPb->getWhere());
        $subBoxWhere = implode(' AND ', $subBoxPb->getWhere());

        $sql = "
            SELECT tc.marking_user_id AS user_id
            FROM assessment t_a
            JOIN task t on t_a.id = t.assessment_id
            JOIN task_completion tc on t.id = tc.task_id
            WHERE {$taskWhere}
              UNION
            SELECT aa.reviewer_id AS user_id
            FROM assessment q_a
            JOIN assess q ON q.assessment_id = q_a.id
            JOIN assess_attempt aa
            WHERE {$quizWhere}
              UNION
            SELECT sr.marker AS user_id
            FROM assessment sb_a
            JOIN submission_box sb on sb_a.id = sb.assessment_id
            JOIN submission_return sr on sb.id = sr.box_id
            WHERE {$subBoxWhere}
        ";

        $params = array_merge($taskPb->getParams(), $quizPb->getParams(), $subBoxPb->getParams());
        return $this->assessmentMapper->getDatabase()->query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }
}
