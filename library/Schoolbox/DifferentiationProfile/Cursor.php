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
    public function __construct(
        private User $viewer,
        private Finder $finder,
        private array|Collection $completedFilters,
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

        $lastRecord = $collection->last();
        if (!isset($lastRecord)) {
            return null;
        }



        return $this->codec->encode([
            's.chosen_name' => $lastRecord->getStudent()->getGivenName(),
            's.lastname'    => $lastRecord->getStudent()->getLastName(),
            'p.student_id' => $lastRecord->getStudent()->getId(),
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
