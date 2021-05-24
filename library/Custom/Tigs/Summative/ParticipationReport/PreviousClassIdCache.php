<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Class PreviousClassIdCache
 *
 * Find a list of folder ids that a student was previously in,
 * that had at least one published feedback,
 * but the student is no longer in this class
 *
 * Used explicitly in the reporting screens to show a particular
 * comment phrase on-screen for certain folders that can appear
 * in that report.
 *
 * @package Custom\Tigs\Summative\ParticipationReport
 */
class PreviousClassIdCache
{

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * A list of folder ids that at student was in, had a published feedback,
     * but is no longer in
     *
     * @var array
     */
    private $previousClassIds = [];


    /**
     * PreviousClassIds constructor.
     *
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(Folder\Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Find a list of folder ids that a student was previously in,
     * that had at least one published feedback,
     * but the student is no longer in this class
     *
     * Note that $folders is a list of all the folders that contain
     * participation records for a student, which is pretty much everywhere
     * that student has been, regardless of whether they have submissions
     * and/or feedbacks in those places
     *
     * @param User              $student
     * @param GenericCollection $folders
     * @param GenericCollection $currentClasses
     * @param User|null         $viewer
     * @param string|null       $roleTypeOverride
     * @return array
     * @throws \PDOException|\DomainException
     */
    public function find(
        User $student,
        GenericCollection $folders,
        GenericCollection $currentClasses,
        ?User $viewer,
        ?string $roleTypeOverride
    ): array {

        /* For SA-10636 we need to filter this set of $folders even further; classes to show
         * are a function of their submissions/feedbacks and feedbacks' visibilities
         */
        $currentClassIds = $currentClasses->getPropertyValues('id');

        /* Find all the participating classes minus the ones they're in now
         * This subtraction gives us all their old classes from which they moved
         *
         * Initially, presume them all to be hidden (excluded) unless deemed otherwise
         */
        $participatingClassIds = $folders->getPropertyValues('id');

        /* All their old classes from which they moved
         * The next set of clauses aims to reduce this set further
         */
        $oldClassIds = array_diff($participatingClassIds, $currentClassIds);

        /* If a student has a published feedback for an assessment in a particular folder,
         * and the visibility of that feedback was students or parents, show it
         */
        $statement = $this->folderMapper->getDatabase()->prepare('
            SELECT DISTINCT ar.folder_id
            FROM `submission_return` sr
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            INNER JOIN v_assessment_relation ar ON sb.assessment_id = ar.assessment_id
            INNER JOIN folder f ON ar.folder_id = f.id
            INNER JOIN assessment a ON ar.assessment_id = a.id
            WHERE sr.`owner` = :ownerId
            AND sr.deleted_at IS NULL
            AND sr.published_at IS NOT NULL
            AND sb.mark_visibility >= :visibility
            AND f.deleted_at IS NULL
            AND a.deleted_at IS NULL
        ');

        /* If roleTypeOverride was provided, use that version
         * Otherwise infer from the currently-logged-in user's roletype
         */
        $type = $roleTypeOverride ?? ($viewer ? $viewer->getRole()->getType() : null);

        switch ($type) {
            /* If the report is being viewed by a student
             */
            case Role::STUDENT:
                $statement->execute([
                    'ownerId' => $student->getId(),
                    'visibility' => IAssessable::MARK_VISIBILITY_STUDENT
                ]);
                break;

            /* If the report is being viewed by a parent
             */
            case Role::PARENT:
                $statement->execute([
                    'ownerId' => $student->getId(),
                    'visibility' => IAssessable::MARK_VISIBILITY_PARENT_STUDENT
                ]);
                break;

            /* If the report is being viewed by a staff
             */
            case Role::STAFF:
                $statement->execute([
                    'ownerId'    => $student->getId(),
                    'visibility' => IAssessable::MARK_VISIBILITY_PRIVATE
                ]);
                break;

            /* Can't figure out the roletype; just die, because this should be impossible
             */
            default:
                throw new \DomainException('Unknown role type for report access');
        }

        $keepIds = [];

        foreach ($statement as $row) {
            $keepIds[] = $row['folder_id'];
        }

        /* Manufacture an intersect; these are the old classes we need to keep
         * because the current user can view some kind of feedback in them
         */
        $this->previousClassIds = array_intersect($oldClassIds, $keepIds);

        /* Give this as output, but also remember it, because we'll need it later
         */
        return $this->previousClassIds;
    }


    /**
     * @return array
     */
    public function getIds(): array
    {
        return $this->previousClassIds;
    }
}
