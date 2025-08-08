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
use Schoolbox\Pastoral\Type\TypeAbstract;
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
        $records->map(function (Model $record) use ($defaultExpiry): void {
            $expiryDate = $this->calculateExpireDate(
                $record->getCreatedAt(),
                $defaultExpiry,
                $record->getStudent()->getYear()
            );
            $record->setExpiresAt($expiryDate);
            $this->pastoralMapper->save($record);
        });
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

        if (PastoralType::PASTORAL_EXPIRY['academic period'] === $defaultExpiry) {
            $expiryDate = $this->loadAcademicPeriod($fromDate, $yearLevel)->getDisplayFinish();
        } else if (PastoralType::PASTORAL_EXPIRY['academic year'] === $defaultExpiry) {
            $expiryDate = (new Alaress_Date_Format($fromDate))->endOfSchoolYear();
        } else if (PastoralType::PASTORAL_EXPIRY['calendar year'] === $defaultExpiry) {
            $expiryDate = (new Alaress_Date_Format($fromDate))->endOfYear();
        } else {
            $expiryDate = (new Alaress_Date_Format($fromDate))->modify("+{$defaultExpiry}");
        }
        return $expiryDate;
    }
}
