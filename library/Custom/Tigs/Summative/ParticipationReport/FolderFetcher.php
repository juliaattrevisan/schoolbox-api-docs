<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use function array_filter;
use function array_map;
use function array_merge;
use function count;
use Custom\Tigs\Summative\StudentAttributes;
use function explode;
use function preg_match;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use External_UserInterface;
use Schoolbox\User;
use Schoolbox\Assessment\Term;
use Schoolbox\Mapper;
use UnexpectedValueException;

/**
 * Responsible for fetching the set of folders a student is/was enrolled in
 */
class FolderFetcher
{

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var External_UserInterface */
    private $externalUser;


    /**
     * @param Folder\Mapper          $folderMapper
     * @param External_UserInterface $externalUser
     */
    public function __construct(Folder\Mapper $folderMapper, External_UserInterface $externalUser)
    {
        $this->folderMapper = $folderMapper;
        $this->externalUser = $externalUser;
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
     * @return GenericCollection|Folder\Model[]
     * @throws Mapper\DefinitionException
     * @throws UnexpectedValueException
     */
    public function findForStudent(User $student, StudentAttributes $attributes, ?string $reportDate, ?string $excludedClassCodes): GenericCollection
    {
        // Always fetch folders with interactions
        $term = (new Term\Model())
            ->setStart($attributes->getCurriculumSemesterStart())
            ->setDisplayFinish($attributes->getCurriculumSemesterFinish())
            ->setActualFinish($attributes->getCurriculumSemesterFinish());

        $folders = $this->folderMapper->findFoldersWithInteractionsForStudent($student, $term);

        // Only retain folders that are either current or rolled-over classes
        $folders = $folders->filter(static function (Folder\Model $folder) {
            return $folder->isClass() || $folder->wasClass();
        });

        // If current report, also fetch enrolled folders
        if (null === $reportDate) {
            $folders = $folders->mergeUnique(
                $this->folderMapper->findAllBySubjectList($this->externalUser->getSubjectList($student)),
                $this->folderMapper->getIdentifierProperty()
            );
        }

        $exclusions = array_filter(array_map('\trim', explode(',', $excludedClassCodes ?? '')));
        foreach ($folders as $idx => $folder) {
            if ($this->isFolderExcluded($folder, $exclusions)) {
                $folders->offsetUnset($idx);
            }
        }

        $folders->indexBy($this->folderMapper->getIdentifierProperty())
                ->sortByCallback(static function (Folder\Model $a, Folder\Model $b): int {
                    return \strnatcasecmp($a->getName(), $b->getName());
                }, true);

        return $folders;
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
}
