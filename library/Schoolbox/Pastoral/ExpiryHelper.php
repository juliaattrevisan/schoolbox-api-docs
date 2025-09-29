<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\Mapper\Exception;
use Schoolbox\Pastoral\Type\TypeAbstract;
use Schoolbox\YearLevel\Model as YearLevel;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Alaress_Date_Format;

class ExpiryHelper
{
    public const int RECORD_CHUNK_SIZE = 20;
    public function __construct(
        private PastoralMapper $pastoralMapper,
        private TermMapper $termMapper,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Updating the expiry date of all matched records by extending from the date they were created on
     *
     * @param int         $typeId
     * @param int|null    $subtypeId
     * @param string|null $defaultExpiry
     * @return void
     * @throws Exception
     */
    public function updateDefaultExpireAt(int $typeId, ?int $subtypeId, ?string $defaultExpiry): void
    {
        $pb = $this->pastoralMapper->getPredicateBuilder();

        $pb->equals('type_id', $typeId);

        if (null !== $subtypeId) {
            $pb->equals('subtype_id', $subtypeId);
        } else {
            $pb->isNull('subtype_id');
        }

        $records = $this->pastoralMapper->findByPredicates($pb);

        $expiry = $records->reduce(function (array $carry, Model $record) use ($defaultExpiry) {
            $expiryDate = $this->calculateExpireDate(
                $record->getCreatedAt(),
                $defaultExpiry,
                $record->getStudent()->getYear()
            );
            $carry[$record->getId()] = $expiryDate->getDatabaseDate();
            return $carry;
        }, []);

        if (!$this->bulkUpdateExpiry($expiry)) {
            throw new \RuntimeException('Failed to update expiry dates for pastoral records');
        }
    }

    /**
     * Bulk update the expiry dates for multiple records
     *
     * @param array $expiry An associative array of record IDs and their corresponding expiry dates (or null for no expiry)
     * @param int   $chunkSize Number of records to process in each chunk
     * @return bool True on success, false on failure
     */
    public function bulkUpdateExpiry(array $expiry, int $chunkSize = self::RECORD_CHUNK_SIZE): bool
    {
        if (empty($expiry)) {
            $this->logger?->info('No records to update for default expiry.');
            return true;
        }

        /* Chunking the array for batch process to avoid too large queries
         */
        $chunks = array_chunk($expiry, $chunkSize, true);
        try {
            $this->logger?->info(sprintf('Updating default expiry for %d records...', count($expiry)));
            foreach ($chunks as $chunk) {
                $this->logger?->info(sprintf('Processing chunk of %d records...', count($chunk)));

                $caseClauses = [];
                $params      = [];

                foreach ($chunk as $id => $expiryDate) {
                    $caseClauses[] = "WHEN id = ? THEN ?";
                    $params[]      = $id;
                    $params[]      = $expiryDate ?? null;
                }

                $sql = sprintf(
                    "UPDATE pastoral SET expires_at = CASE %s END WHERE id IN (%s)",
                    implode(' ', $caseClauses),
                    implode(',', array_fill(0, count($chunk), '?'))
                );

                // Add IDs to params for WHERE clause
                $params = array_merge($params, array_keys($chunk));

                $this->pastoralMapper->getDatabase()->executeQuery($sql, $params);

                // Free memory after each chunk
                unset($chunk, $caseClauses, $params);
            }
        } catch (\Exception $e) {
            $this->logger?->error('Error occurred during bulk update of expiry dates: ' . $e->getMessage());
            return false;
        }
        $this->logger?->info(sprintf('Default expiry update completed for %d records.', count($expiry)));
        return true;
    }

    /**
     * Find the current academic period of the student that the record is assigning to
     *
     * @param Alaress_Date_Format|null $createdAt
     * @param YearLevel|null           $yearLevel
     * @return TermModel
     * @throws Exception
     */
    private function loadAcademicPeriod(?Alaress_Date_Format $createdAt, ?YearLevel $yearLevel): TermModel
    {
        return $this->termMapper->findForDateAndYearLevel(
            $createdAt ?? Alaress_Date_Format::now(),
            $yearLevel
        );
    }

    /**
     * Calculating the expiry date
     *
     * @param Alaress_Date_Format $fromDate
     * @param string|null         $defaultExpiry
     * @param YearLevel|null      $yearLevel
     * @return Alaress_Date_Format|null
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function calculateExpireDate(Alaress_Date_Format $fromDate, ?string $defaultExpiry, ?YearLevel $yearLevel): ?Alaress_Date_Format
    {
        /* No expiry could be either null or empty string
         */
        if ('' === (string)$defaultExpiry) {
            return null;
        }

        /* Additional check to make sure that the value passed was part of the valid expiry period
         */
        if (!in_array($defaultExpiry, TypeAbstract::PASTORAL_EXPIRY)) {
            throw new \InvalidArgumentException(
                sprintf('Default expiry is not valid. %s was given.', $defaultExpiry)
            );
        }

        return match ($defaultExpiry) {
            TypeAbstract::PASTORAL_EXPIRY[TypeAbstract::PASTORAL_ACADEMIC_PERIOD] => $this->loadAcademicPeriod($fromDate, $yearLevel)->getDisplayFinish(),
            TypeAbstract::PASTORAL_EXPIRY[TypeAbstract::PASTORAL_ACADEMIC_YEAR] => (new Alaress_Date_Format($fromDate))->endOfSchoolYear(),
            TypeAbstract::PASTORAL_EXPIRY[TypeAbstract::PASTORAL_CALENDAR_YEAR] => (new Alaress_Date_Format($fromDate))->endOfYear(),
            /* End of day will expire on the same day
             */
            TypeAbstract::PASTORAL_EXPIRY[TypeAbstract::PASTORAL_END_OF_DAY] => (new Alaress_Date_Format($fromDate)),
            default => (new Alaress_Date_Format($fromDate))->modify("+{$defaultExpiry}"),
        };
    }
}
