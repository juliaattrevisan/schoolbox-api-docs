<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Custom\Tigs\Summative\StudentAttributes;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use External_UserInterface;
use Schoolbox\User;
use Schoolbox\Assessment\Term;
use UnexpectedValueException;

use function explode;
use function preg_match;
use function array_filter;
use function array_map;
use function array_merge;
use function count;

/**
 * Responsible for fetching the set of folders a student is/was enrolled in
 */
class FolderFetcher
{
    /**
     * Year level group folders always have a class code with this suffix: it is
     * prefixed with their year level, e.g. "10YG_1".
     *
     * TODO make this configurable
     */
    private const YEAR_LEVEL_GROUP_FOLDER_CODE_SUFFIX = 'YG_1';

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var External_UserInterface */
    private $externalUser;


    /**
     * @var PreviousClassIdCache
     */
    private $previousClassIdCache;


    /**
     * @var FoldersWithCourses
     */
    private FoldersWithCourses $foldersWithCourses;


    /**
     * @param Folder\Mapper          $folderMapper
     * @param External_UserInterface $externalUser
     */
    public function __construct(Folder\Mapper $folderMapper, External_UserInterface $externalUser)
    {
        $this->folderMapper = $folderMapper;
        $this->externalUser = $externalUser;

        /* A cache for remembering previous class ids
         */
        $this->previousClassIdCache = new PreviousClassIdCache($folderMapper);

        /* A cache for remembering folders with courses
         */
        $this->foldersWithCourses = new FoldersWithCourses($folderMapper);
    }


    /**
     * Returns the set of folders a user was enrolled in at the given point in time.
     *
     * If $reportDate is NULL, returns current enrolled folders
     *
     * Folders are indexed, and sorted by name (ignoring case)
     *
     * @param User              $student
     * @param StudentAttributes $attributes
     * @param string|null       $reportDate
     * @param null|string       $excludedClassCodes
     * @param User|null         $viewer
     * @param string|null       $roleTypeOverride
     * @return GenericCollection|Folder\Model[]
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \PDOException
     */
    public function findForStudent(User $student, StudentAttributes $attributes, ?string $reportDate, ?string $excludedClassCodes, ?User $viewer, ?string $roleTypeOverride): GenericCollection
    {
        // Always fetch folders with interactions
        $term = (new Term\Model())
            ->setStart($attributes->getCurriculumSemesterStart())
            ->setDisplayFinish($attributes->getCurriculumSemesterFinish())
            ->setActualFinish($attributes->getCurriculumSemesterFinish());

        /* This actually returns folders with Participations, findFoldersWithInteractionsForStudent()
         * is probably not the best function name for what it does.
         */
        $folders = $this->folderMapper->findFoldersWithInteractionsForStudent($student, $term);

        // Only retain folders that are either current or rolled-over classes
        $folders = $folders->filter(static function (Folder\Model $folder) {
            return $folder->isClass() || $folder->wasClass();
        });

        /* For SA-10636 we need to filter this set of $folders even further; classes to show
         * are a function of their submissions/feedbacks and feedbacks' visibilities
         */
        $currentClasses = $this->folderMapper->findAllBySubjectList($this->externalUser->getSubjectList($student));
        $preservedClassIds = $this->previousClassIdCache->find($student, $folders, $currentClasses, $viewer, $roleTypeOverride);

        /* Iterate through folders, unsetting everything outside of $preservedClassIds
         */
        $classIds = array_merge($preservedClassIds, $currentClasses->getPropertyValues('id'));

        foreach ($folders as $idx => $folder) {
            if (!\in_array($folder->getId(), $classIds, false)) {
                $folders->offsetUnset($idx);
            }
        }

        /* Then slap it together with the current student's classes, if reportDate not set
         *
         * Either way, this should give us only relevant previous classes as it filters out
         * all previous classes that had no useful feedbacks in them
         */

        // If current report, also fetch enrolled folders
        if (null === $reportDate) {
            $folders = $folders->mergeUnique(
                $currentClasses,
                $this->folderMapper->getIdentifierProperty()
            );
        }

        /* This is a human-curated blacklist which specifically excludes folders.
         * The folders to be excluded are maintained through a user interface, by a human
         * but the humans can't keep up with the exclusions using this mechanic...
         */
        $exclusions = array_filter(array_map('\trim', explode(',', $excludedClassCodes ?? '')));

        /* ...which means we programmatically exclude folders that would otherwise be
         * blacklisted by a human through the ui. We only care about Folders With Courses;
         * Folders Without Courses can be ignored
         */
        $foldersWithCourses = $this->foldersWithCourses->find();

        foreach ($folders as $idx => $folder) {
            /* This folder was blacklisted via human curation
             */
            if ($this->isFolderExcluded($folder, $exclusions)) {
                $folders->offsetUnset($idx);
            }

            /* Only care about folders with course implementations
             */
            if (!isset($foldersWithCourses[$folder->getId()])) {
                $folders->offsetUnset($idx);
            }
        }

        /* Sort alphabetically
         */
        $folders->indexBy($this->folderMapper->getIdentifierProperty())
            ->sortByCallback(static function (Folder\Model $a, Folder\Model $b): int {
                return \strnatcasecmp($a->getName(), $b->getName());
            }, true);

        return $folders;
    }


    /**
     * Search for the student's year group folder (if there is one in which they
     * have participated within the attributes' semester).
     *
     * @param User              $student
     * @param StudentAttributes $attributes
     * @return Folder\Model|null
     */
    public function findYearGroupFolder(User $student, StudentAttributes $attributes): ?Folder\Model
    {
        // Always fetch folders with interactions
        $term = (new Term\Model())
            ->setStart($attributes->getCurriculumSemesterStart())
            ->setDisplayFinish($attributes->getCurriculumSemesterFinish())
            ->setActualFinish($attributes->getCurriculumSemesterFinish());

        /* This actually returns folders with Participations, findFoldersWithInteractionsForStudent()
         * is probably not the best function name for what it does.
         */
        $folders = $this->folderMapper
            ->findFoldersWithInteractionsForStudent($student, $term)
            ->filter(static function (Folder\Model $folder) {
                return $folder->isClass() || $folder->wasClass();
            });

        // Only retain folders that are either current or rolled-over classes
        $yearGroupCode = sprintf('%d%s', $attributes->getYearLevel(), self::YEAR_LEVEL_GROUP_FOLDER_CODE_SUFFIX);
        foreach ($folders as $folder) {
            foreach ($folder->getCodes() as $code) {
                if (!$code->isDeleted() && $code->getCode() === $yearGroupCode) {
                    return $folder;
                }
            }
            foreach ($folder->getRolledOverCodes() as $code) {
                if ($code === $yearGroupCode) {
                    return $folder;
                }
            }
        }
        return null;
    }


    /**
     * Returns whether a folder should be excluded from reports due to configured class code exclusions
     *
     * Excludes a folder if either a current or archived class code matches one of the exclusions.
     * We need to check against both current and archived codes because their is only one exclusion list, and it
     * applies to all reports (but current and historical).
     *
     * @param Folder\Model $folder
     * @param string[]     $exclusions
     * @return bool
     */
    private function isFolderExcluded(Folder\Model $folder, array $exclusions): bool
    {
        if (!count($exclusions)) {
            return false;
        }

        // Current folder codes
        $codes = $folder->getCodes()->getPropertyValues('code');

        // Rolled-over folder codes
        if (preg_match('/\[([^\]]+)\]/', $folder->getName(), $matches)) {
            $codes = array_merge($codes, explode(',', $matches[1]));
        }

        foreach ($codes as $code) {
            foreach ($exclusions as $exclusion) {
                if ($exclusion === $code) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * @return array
     */
    public function getPreviousClassIds(): array
    {
        return $this->previousClassIdCache->getIds();
    }
}
