<?php

namespace Schoolbox\Assessment\Course\Api;

use Alaress\Codec\ICodec;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use InvalidArgumentException;

class Helper
{
    public function __construct(
        private CourseSpecificationMapper $courseSpecificationMapper,
        private FolderMapper $folderMapper,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private Access $access,
        private User $user,
        private ?string $search,
        private int $limit,
        private ?ICodec $codec,
    ) {
    }

    /**
     * @param string|null $encodedCursor
     * @return array
     * @throws MapperException
     */
    public function getCourseDate(?string $encodedCursor = null): array
    {
        $pb = $this->courseSpecificationMapper->getPredicateBuilder();
        $findOptions = [
            'order' => [
                "f.name" => 'asc',
                "{$this->courseSpecificationMapper->getTable()}.{$this->courseSpecificationMapper->getIdentifierColumn()}" => 'asc',
            ],
            'limit' => $this->limit,
        ];

        if (strlen((string)$this->search) > 0) {
            $pb->like(
                'f.name',
                "%{$this->courseSpecificationMapper->getDatabase()->getQuoter()->escapeForLike(
                        $this->search
                    )}%"
            );
        }

        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
            /* The search term should be the same to avoid confusion in the search results
             */
            if (null === $this->search && $cursor['search'] !== $this->search) {
                throw new InvalidArgumentException('Query must be provided with a cursor');
            }
            $pb->greaterThan(
                "f.name",
                $cursor['name']
            );
        }

        $allCourses = $this->courseSpecificationMapper->findByPredicates($pb, $findOptions);

        $data = [];

        foreach ($allCourses as $currentCourse) {
            // general search ignores Courses outside Faculty
            if (null === $this->search) {
                try {
                    if (!$this->folderMapper->isDescendant($currentCourse->getFolder(), $this->courseSpecificationMapper->getFacultyRootFolder())) {
                        continue;
                    }
                } catch (MapperException $e) {
                    // can't detect if it's in faculty area because an ancestor is deleted. For our purposes, consider this deleted / irrelevant
                    continue;
                }
            }

            /* For each of the courses found in $data['courses'], decide if it's a courseSpec
             * (it should already be, but we're specifically editing courseSpecs only
             * in this usage - if you pollute the data, don't get sick from it)
             *
             * Then look at its folder instance and find out if you have WRITE+ access to it
             * If you have write access to the courseSpec's folder context, you can modify
             * the course - otherwise, deny the link but show it in the index anyway I guess
             *
             * (Staff members who don't have permissions to edit might still find it useful
             * to see what's in here)
             */
            $courseFolderAccess = $this->access->getFolderAccess($this->user, $currentCourse->getFolder()->getId());
            if ($courseFolderAccess > UserAccess::ACCESS_READ) {
                $data['folderAccess'][$currentCourse->getFolder()->getId()] = true;
            } elseif ($courseFolderAccess === UserAccess::ACCESS_READ) {
                $data['folderAccess'][$currentCourse->getFolder()->getId()] = false;
            } else {
                continue;
            }

            /* Also store the path to this Course
             */
            try {
                $lineage = $this->getLineage($currentCourse->getFolder());
                $currentCourse->setFolder(
                    $this->getParent($currentCourse->getFolder(), $lineage)
                );
            } catch (MapperException $e) {
                // somehow, against all odds, one of my ancestors has been deleted: show nothing
                continue;
            }

            // Get units for a course
            $units = $this->unitSpecificationMapper->findAllByCourseId($currentCourse->getId());

            // Remove units whose folders aren't accessible by this user
            $currentCourse->setUnits($units->filter(function ($unit) {
                /** @var UnitSpecification $unit */
                return self::confirmAccess($unit->getFolder()->getId(), $this->access, $this->user);
            }));

            // Add course directly into $data
            $data['courses'][] = $currentCourse;
        }

        return $data;
    }

    /**
     * Finding all the parent of the folder
     *
     * @param Folder $course
     * @return Collection
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    private function getLineage(Folder $course): Collection
    {
        return Ancestry::getInstance($this->folderMapper)->findParentsCheaply($course, $this->courseSpecificationMapper->getFacultyRootFolder());
    }

    /**
     * Link up the children to their parent folders
     *
     * @param Folder     $folder
     * @param Collection $lineage
     * @return Folder
     */
    private function getParent(Folder $folder, Collection $lineage): Folder
    {
        $return = $folder;

        foreach (array_reverse($lineage->getAll()) as $parent) {
            //Set parent to null when reached the faculty root folder
            if ($parent->getParent()?->getName() === null) {
                $parent->setParent(null);
            }
            $return->setParent($parent);
            $return = $return->getParent();
        }

        return $folder;
    }

    /**
     * Checking user's access on the folder
     *
     * @param int    $folderId
     * @param Access $access
     * @param User   $user
     * @param int    $min
     * @return bool
     * @throws MapperException
     */
    private static function confirmAccess(
        int $folderId,
        Access $access,
        User $user,
        int $min = UserAccess::ACCESS_READ
    ): bool {
        return $access->getFolderAccess($user, $folderId) >= $min;
    }

    /**
     * Gets the suggested course links to A FULLY HYDRATED folder
     *
     * @param Folder $folder
     * @return Collection
     * @throws MapperException
     */
    public function getLinkSuggestions(Folder $folder): Collection
    {
        // obtain curriculum codes...
        $codes = $folder->getCurriculumNodes()?->getPropertyValues('code');

        // It appears that the curriculumNodes is never null, however it is nullable
        // so we will do a sanity check and exit early if it is ever null
        if (null === $codes) {
            return new Collection();
        }

        // find all the folders that match this code, then filter for coarseness
        $courseFolders = $this->folderMapper->findByCurriculumCode($codes)->filter(function ($result) {
            /** @var Folder $result */
            // TODO: test this with folders and courses which have curriculum codes
            try {
                $this->courseSpecificationMapper->findByFolderId($result->getId());
                return true;
            } catch (MapperException $e) {
                return false;
            }
        });

        $courseFolderIds = $courseFolders->getPropertyValues('id');

        $courseSpecs = $this->courseSpecificationMapper->findByColumn('folder_id', $courseFolderIds);

        return $courseSpecs;
    }
}
