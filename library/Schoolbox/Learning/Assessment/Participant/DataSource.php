<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant;

use External_ClassInterface;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * The single source of truth for assessment participants.
 */
class DataSource
{
    /**
     * Data source for class lists
     *
     * @var External_ClassInterface
     */
    protected $externalDb;

    /**
     * Data source for group lists
     *
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * Data source for users themselves
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor
     *
     * @param External_ClassInterface $externalDb
     * @param UserAccessMapper        $userAccessMapper
     * @param UserMapper              $userMapper
     */
    public function __construct(
        External_ClassInterface $externalDb,
        UserAccessMapper $userAccessMapper,
        UserMapper $userMapper
    ) {
        $this->externalDb       = $externalDb;
        $this->userAccessMapper = $userAccessMapper;
        $this->userMapper       = $userMapper;
    }

    /**
     * Find all Participants for a given assessment context.
     *
     * This is a union of:
     * * any students in the context's folder's class
     * * any read-level members of the context's folder's group
     *
     * @param AssessmentContext $context
     * @return Collection|Participant[]
     */
    public function findForContext(AssessmentContext $context)
    {
        // This will use:
        // External_ClassInterface
        // UserAccessMapper

        $participants = new Collection();

        if ($context instanceof UnitImplementation) {
            // Unit implementations have no participants
            return $participants;
        }

        $folder = $context->getFolder();

        $users = new Collection();

        // Class members: convert to list of users
        $classCodes = $this->externalDb->getStudents($folder->getCodeString());
        if (!empty($classCodes)) {
            $users->merge($this->userMapper->findByColumn('synergy_id', array_column($classCodes, 'synergy_id')));
        }

        // I want to search for ALL read-level users, not just students
        // Deliberately don't search external DB, I have already done that
        $users->mergeUnique(
            $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false),
            'id'
        );

        // Consider caching $users

        // Convert $users into a participant list

        return $participants;
    }

    /**
     * Find all Participants for a given assessment.
     *
     * This is a union of:
     * * the result of `findForContext()` for this assessment's context
     * * anyone who has submitted a Response for this assessment
     * * anyone who has been given Feedback for this assessment
     *
     * @param IAssessable $assessment
     * @return Collection|Participant[]
     */
    public function findForAssessment(IAssessable $assessment)
    {
        // This will use:
        // various data sources depending on the variant of $assessment
        // This will probably retrieve all Feedbacks and Responses for $assessment
    }
}
