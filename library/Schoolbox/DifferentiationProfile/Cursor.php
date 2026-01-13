<?php

namespace Schoolbox\DifferentiationProfile;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Filter\Filters\DifferentiationProfile\Finder;
use Schoolbox\User;

class Cursor implements ICursor
{
    public const string CURSOR_DEFAULT_ORDER_BY = 'student';
    public const string CURSOR_ORDER_BY_UPDATED_AT = 'updated_at';

    public const string CURSOR_ORDER_BY_STUDENT_ID_COLUMN = 'p.student_id';
    public const string CURSOR_ORDER_BY_UPDATED_AT_COLUMN = 'differentiation_profiles.updated_at';

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

        return $this->finder->findProfilesForStudentsByFilters(
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
        if ($this->orderBy === self::CURSOR_ORDER_BY_UPDATED_AT) {
            return $this->codec->encode([
                self::CURSOR_ORDER_BY_UPDATED_AT_COLUMN => $lastRecord->getUpdatedAt()?->getDatabase(),
                self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getStudent()->getId(),
            ]);
        }

        /* Return student collection if default order by is used
         */
        return $this->codec->encode([
            's.chosen_name' => $lastRecord->getStudent()->getGivenName(),
            's.lastname'    => $lastRecord->getStudent()->getLastName(),
            self::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => $lastRecord->getStudent()->getId(),
        ]);
    }

    public function current(Collection $collection): ?string
    {
        return null;
    }

    public function retrieveResultCount(): ?int
    {
        return $this->finder->findProfilesForStudentsByFilters(
            $this->viewer,
            $this->completedFilters,
            null,
            null,
            null,
        )->count();
    }
}
