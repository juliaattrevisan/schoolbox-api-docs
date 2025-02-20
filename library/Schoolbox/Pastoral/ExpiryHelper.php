<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\Mapper\Exception;
use Schoolbox\YearLevel\Model as YearLevel;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Alaress_Date_Format;

class ExpiryHelper
{
    public function __construct(
        private PastoralMapper $pastoralMapper,
        private TermMapper $termMapper,
    ) {
    }

    /**
     * Updating the expiry date of all matched records by extending from the date they were created on
     *
     * @param int         $typeId
     * @param int|null    $subtypeId
     * @param string|null $defaultExpiry
     * @return void
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
        $records->map(function (Model $record) use ($defaultExpiry) {
            $expiryDate = $this->getActualExpireDate($record, $defaultExpiry);
            $record->setExpiresAt($expiryDate);
            $this->pastoralMapper->save($record);
        });
    }

    /**
     *
     * @param Model       $record
     * @param string|null $defaultExpiry
     * @return Alaress_Date_Format|null
     * @throws Exception
     */
    private function getActualExpireDate(Model $record, ?string $defaultExpiry): ?Alaress_Date_Format
    {
        /* No expiry
         */
        if (null === $defaultExpiry) {
            return null;
        }

        return $this->calculateExpireDate(
            $record->getCreatedAt(),
            $defaultExpiry,
            $record->getStudent()->getYear()
        );
    }

    /**
     * Find the current academic period of the student that the record is assigning to
     *
     * @param Alaress_Date_Format|null $createdAt
     * @param YearLevel                 $yearLevel
     * @return TermModel
     * @throws Exception
     */
    private function loadAcademicPeriod(?Alaress_Date_Format $createdAt, YearLevel $yearLevel): TermModel
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
     * @param string               $defaultExpiry
     * @param YearLevel            $yearLevel
     * @return Alaress_Date_Format
     * @throws Exception
     */
    public function calculateExpireDate(Alaress_Date_Format $fromDate, string $defaultExpiry, YearLevel $yearLevel): Alaress_Date_Format
    {
        if (PastoralType::PASTORAL_EXPIRY['academic period'] === $defaultExpiry) {
            $expiryDate = $this->loadAcademicPeriod($fromDate, $yearLevel)->getDisplayFinish();
        } else if (PastoralType::PASTORAL_EXPIRY['academic year'] === $defaultExpiry) {
            $expiryDate = (new Alaress_Date_Format($fromDate))->getStartOfSchoolYear()->addYears(1);
        } else if (PastoralType::PASTORAL_EXPIRY['calendar year'] === $defaultExpiry) {
            $expiryDate = (new Alaress_Date_Format($fromDate))->addYears(1)->startOfYear();
        } else {
            $expiryDate = (new Alaress_Date_Format($fromDate))->modify("+{$defaultExpiry}");
        }
        return $expiryDate;
    }
}
