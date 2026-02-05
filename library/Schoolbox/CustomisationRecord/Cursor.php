<?php

namespace Schoolbox\CustomisationRecord;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Assessment\Participant\Model;
use Schoolbox\Filter\Filters\CustomisationRecord\Finder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplModel;
use Schoolbox\User;

class Cursor implements ICursor
{
    public const string CURSOR_DEFAULT_ORDER_BY = 'user';
    public const string CURSOR_ORDER_BY_CREATED_AT = 'created';
    public const string CURSOR_ORDER_BY_CLASS_NAME = 'class';
    public const string CURSOR_ORDER_BY_UNIT_NAME = 'unit';
    public const string CURSOR_ORDER_BY_CUSTOMISATION_TYPE = 'type';

    public const string CURSOR_ORDER_BY_STUDENT_ID_COLUMN = 'user_id';
    public const string CURSOR_ORDER_BY_CREATED_AT_COLUMN = 'created_at';
    public const string CURSOR_ORDER_BY_CLASS_NAME_COLUMN = 'f.name';
    public const string CURSOR_ORDER_BY_UNIT_NAME_COLUMN = 'cui.title';
    public const string CURSOR_ORDER_BY_CUSTOMISATION_TYPE_COLUMN = 'pp.customisation_type';

    public function __construct(
        private User $viewer,
        private Finder $finder,
        private array|Collection $completedFilters,
        private ?string $orderBy = self::CURSOR_DEFAULT_ORDER_BY,
        private ?array $order = null,
        private ?int $limit = null,
        private ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        if ($encodedCursor !== null) {
            $decoded = $this->codec->decode($encodedCursor);
        }

        return $this->finder->findCustomisationForStudentsByFilters(
            $this->viewer,
            $this->completedFilters,
            $decoded ?? null,
            $this->order,
            $this->limit,
        );
    }

    public function next(Collection $collection): ?string
    {
        /* Return null if there are not enough records
         * to warrant a next cursor which indicates end of results
         */
        if ($collection->count() < $this->limit) {
            return null;
        }

        /** @var Model $lastRecord */
        $lastRecord = $collection->last();
        if (!isset($lastRecord)) {
            return null;
        }

        /* Return differentiation profile collection if ordered by updated at
         */
        if ($this->orderBy === self::CURSOR_ORDER_BY_CREATED_AT) {
            return $this->codec->encode([
                self::CURSOR_ORDER_BY_CREATED_AT_COLUMN => $lastRecord->getCreatedAt()?->getDatabase(),
                self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getUser()->getId(),
            ]);
        }

        /** @var UnitImplModel $context */
        $context = $lastRecord->getPlan()->getAssessment()->getContextUnitImplementation();
        if (null === $context) {
            /* Assessment in a project, then look at the project context
             */
            $context = $lastRecord->getPlan()->getAssessment()->getContextProject()->getAssessmentModel()->getContextUnitImplementation();
        }

        if ($this->orderBy === self::CURSOR_ORDER_BY_CLASS_NAME) {
            return $this->codec->encode([
                self::CURSOR_ORDER_BY_CLASS_NAME_COLUMN => $context?->getTitle(),
                self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getUser()->getId(),
            ]);
        }

        if ($this->orderBy === self::CURSOR_ORDER_BY_UNIT_NAME) {
            return $this->codec->encode([
                self::CURSOR_ORDER_BY_UNIT_NAME_COLUMN => $context?->getFolder()?->getTitle(),
                self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getUser()->getId(),
            ]);
        }

        if ($this->orderBy === self::CURSOR_ORDER_BY_CUSTOMISATION_TYPE) {
            return $this->codec->encode([
                self::CURSOR_ORDER_BY_CUSTOMISATION_TYPE_COLUMN => $lastRecord->getPlan()->getCustomisationType(),
                self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getUser()->getId(),
            ]);
        }
        /* Return student collection if default order by is used
         */
        return $this->codec->encode([
            'u.chosen_name' => $lastRecord->getUser()->getGivenName(),
            'u.lastname'    => $lastRecord->getUser()->getLastName(),
            self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getUser()->getId(),
        ]);
    }

    public function current(Collection $collection): ?string
    {
        return null;
    }

    public function retrieveResultCount(): ?int
    {
        return $this->finder->findCustomisationForStudentsByFilters(
            $this->viewer,
            $this->completedFilters,
            null,
            null,
            null,
        )->count();
    }
}
