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
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Pastoral\Mapper as PastoralMapper;

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
     * @return \Alaress_Date_Format|null
     * @throws Exception
     */
    private function getActualExpireDate(Model $record, ?string $defaultExpiry): ?\Alaress_Date_Format
    {
        /* No expiry
         */
        if (null === $defaultExpiry) {
            return null;
        }

        $createdAt = $record->getCreatedAt();
        if (PastoralType::PASTORAL_EXPIRY['academic period'] === $defaultExpiry) {
            $expiryDate = $this->loadAcademicPeriod($record)->getDisplayFinish();
        } else if (PastoralType::PASTORAL_EXPIRY['academic year'] === $defaultExpiry) {
            $expiryDate = (new \Alaress_Date_Format($createdAt))->getStartOfSchoolYear()->addYears(1);
        } else if (PastoralType::PASTORAL_EXPIRY['calendar year'] === $defaultExpiry) {
            $expiryDate = (new \Alaress_Date_Format($createdAt))->addYears(1)->startOfYear();
        } else {
            $expiryDate = (new \Alaress_Date_Format($createdAt))->modify("+{$defaultExpiry}");
        }

        return $expiryDate;
    }

    /**
     * Find the current academic period of the student that the record is assigning to
     *
     * @param Model      $record
     * @return TermModel
     * @throws Exception
     */
    public function loadAcademicPeriod(Model $record): TermModel
    {
        return $this->termMapper->findForDateAndYearLevel(
            $record->getCreatedAt() ?? \Alaress_Date_Format::now(),
            $record->getStudent()->getYear()
        );
    }
}
