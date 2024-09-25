<?php

namespace Schoolbox\Assessment\Course\Api;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Connector\IConnector;
use Schoolbox\API\Cursor\ICursor;
//use Schoolbox\Assessment\Course\Helper;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use ValueError;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;

class Cursor implements ICursor
{
    /**
     * Minimum number of results returnable from the cursor at once:
     * it must return at least one result.
     */
    public const LIMIT_MIN = 1;

    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 100 results is a sensible maximum.
     */
    public const LIMIT_MAX = 100;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;

    public function __construct(
        private CourseSpecificationMapper $courseSpecificationMapper,
        private FolderMapper $folderMapper,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private Access $access,
        private User $user,
        private ?string $folder,
        private ?string $search,
        private int $limit,
        private ?ICodec $codec = null,
    ) {
        $this->codec = new Base64();
        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(
                sprintf(
                    '$limit must be at least %d: %d provided',
                    self::LIMIT_MIN,
                    $this->limit
                )
            );
        }
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(
                sprintf(
                    '$limit must be at most %d: %d provided',
                    self::LIMIT_MAX,
                    $this->limit
                )
            );
        }
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        $data = $this->getCourseDate($this->search);

//        $data = Helper::getCourseData(
//            $this->courseSpecificationMapper,
//            $this->folderMapper,
//            $this->access,
//            $this->user,
//            $this->unitSpecificationMapper,
//            $this->limit,
//            $encodedCursor ? $this->codec->decode($encodedCursor) : null,
//            $this->search
//        );
//
//        // only offer suggestions if you're not searching
//        if ($this->folder && !$this->search) {
//            $folderModel = $this->folderMapper->findById($this->folder);
//            $data['suggestions'] = Helper::getLinkSuggestions(
//                $folderModel,
//                $this->folderMapper,
//                $this->courseSpecificationMapper,
//            );
//        }
//
        return new Collection($data);
    }

    public function retrieveResultCount(): ?int
    {
        if (null === $this->search) {
            $counts = $this->courseSpecificationMapper->countAll();
        } else {
//            $counts = $this->courseSpecificationMapper->countAllByName($this->search);
        }

        return $counts;
    }

    public function current(Collection $collection): ?string
    {
            return null;
    }

    public function next(Collection $collection): ?string
    {
        if ($collection->count() === 0) {
            return null;
        }

        $id = 1;

        return $this->codec->encode(['id' => $id]);
    }

    public function getCourseDate(string $searchParam = null): array
    {
        //Use the new mapper to get the list of courses
        $findOptions = ['order' => ['f.name' => 'asc']];
        if (null === $searchParam) {
            $allCourses = $this->courseSpecificationMapper->findAll($findOptions);
        } else {
            $allCourses = $this->courseSpecificationMapper->findAllByName($searchParam, $findOptions);
        }

        $data = [];

        foreach ($allCourses as $currentCourse) {
            // general search ignores Courses outside Faculty
            if (null === $searchParam) {
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
//                $data['lineage'][$currentCourse->getId()] = $this->getLineage($currentCourse->getFolder());
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

    private function getLineage(Folder $course): Collection
    {
        return Ancestry::getInstance($this->folderMapper)->findParentsCheaply($course, $this->courseSpecificationMapper->getFacultyRootFolder());
    }

    private function getParent(Folder $folder, Collection $lineage): Folder
    {
        $return = $folder;

        foreach (array_reverse($lineage->getAll()) as $parent) {
            $return->setParent($parent);
            $return = $return->getParent();
        }

        return $folder;
    }

    /**
     * @param int $folderId
     * @param int $min
     * @return bool
     */
    private static function confirmAccess(
        $folderId,
        Access $access,
        User $user,
        $min = UserAccess::ACCESS_READ
    ): bool {
        return $access->getFolderAccess($user, $folderId) >= $min;
    }
}

