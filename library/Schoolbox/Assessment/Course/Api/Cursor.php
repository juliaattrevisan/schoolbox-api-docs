<?php

namespace Schoolbox\Assessment\Course\Api;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Ancestry;
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
        private Ancestry $ancestry,
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
                    'Limit must be at least %d: %d provided',
                    self::LIMIT_MIN,
                    $this->limit
                )
            );
        }
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(
                sprintf(
                    'Limit must be at most %d: %d provided',
                    self::LIMIT_MAX,
                    $this->limit
                )
            );
        }
    }

    /**
     * @inheritdoc
     * @throws MapperException
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $helper = new Helper(
            $this->courseSpecificationMapper,
            $this->folderMapper,
            $this->unitSpecificationMapper,
            $this->ancestry,
            $this->access,
            $this->user,
            $this->search,
            $this->limit,
            $this->codec
        );

        $data = $helper->getCourseDate($encodedCursor);

        // only offer suggestions if you're not searching
        if ($this->folder && !$this->search) {
            $folderModel = $this->folderMapper->findById($this->folder);
            $data['suggestions'] = $helper->getLinkSuggestions(
                $folderModel,
            );
        }

        return new Collection($data);
    }

    /**
     * @inheritdoc
     */
    public function retrieveResultCount(): ?int
    {
        if (null === $this->search) {
            $counts = $this->courseSpecificationMapper->countAll();
        } else {
            $counts = $this->courseSpecificationMapper->countAllByName($this->search);
        }

        return $counts;
    }

    /**
     * @inheritdoc
     */
    public function current(Collection $collection): ?string
    {
            return null;
    }

    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        if ($collection->count() === 0) {
            return null;
        }

        $id = $collection->last()->getId();
        $name = $collection->last()->getFolder()->getName();

        return $this->codec->encode(
            [
                'id' => $id,
                'name' => $name,
                'search' => $this->search,
            ]
        );
    }
}
