<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Count;

use Schoolbox\Collection;
use Schoolbox\Pastoral\Audience\ColorHelper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\User;

class Helper
{
    /**
     * @var PastoralTypeMapper
     */
    private PastoralTypeMapper $pastoralTypeMapper;

    /**
     * @var PastoralSubTypeMapper
     */
    private PastoralSubTypeMapper $pastoralSubTypeMapper;

    /**
     * @var ColorHelper
     */
    private ColorHelper $colorHelper;

    /**
     * @var PastoralDataSource
     */
    private PastoralDataSource $pastoralDataSource;

    /**
     * @var Collection|PastoralType
     */
    private $pastoralTypesWhitelist;

    /**
     * @var Collection|PastoralSubType
     */
    private $pastoralSubTypesWhitelist;

    /**
     * helper to bundle similar counts related to pastoral events of students
     *
     * @param PastoralTypeMapper    $pastoralTypeMapper
     * @param PastoralSubTypeMapper $pastoralSubTypeMapper
     * @param PastoralDataSource    $pastoralDataSource
     */
    public function __construct(PastoralTypeMapper $pastoralTypeMapper, PastoralSubTypeMapper $pastoralSubTypeMapper, PastoralDataSource $pastoralDataSource)
    {
        $this->pastoralTypeMapper    = $pastoralTypeMapper;
        $this->pastoralSubTypeMapper = $pastoralSubTypeMapper;
        $this->pastoralDataSource    = $pastoralDataSource;
        $this->colorHelper           = new ColorHelper();

        $this->pastoralTypesWhitelist = $this->pastoralTypeMapper->findByPredicates(
            $this->pastoralTypeMapper->getPredicateBuilder()->notNull('color')->notNull('icon')->isNull('deleted_at')
        );

        $this->pastoralSubTypesWhitelist = $this->pastoralSubTypeMapper->findByPredicates(
            $this->pastoralSubTypeMapper->getPredicateBuilder()->notNull('color')->notNull('icon')->isNull('deleted_at')
        );
    }

    /**
     * return counts of pastoral events types(and subtypes) for multiple students
     *
     * @param User            $viewer            the logged-in user that would like to inspect this information
     * @param Collection      $students          collection of students we want to count the pastoral events
     * @param Collection|null $typesWhitelist    optional whitelist of pastoral types that should be counted
     * @param Collection|null $subTypesWhitelist optional whitelist of pastoral subtypes that should be counted
     * @return Collection
     */
    public function countStudentsEventsByTypes(User $viewer, Collection $students, ?Collection $typesWhitelist = null, ?Collection $subTypesWhitelist = null): Collection
    {
        /**
         * make sure we are always falling back to a consistent default list of types
         * and subtypes, unless otherwise instructed
         */
        $pastoralTypes    = $typesWhitelist ?? $this->pastoralTypesWhitelist;
        $pastoralSubtypes = $subTypesWhitelist ?? $this->pastoralSubTypesWhitelist;

        $output = new Collection();

        /**
         * go through and grab each of the students pastoral event counts
         */
        foreach ($students as $student) {
            $output->setOffset(
                $student->getId(),
                $this->countStudentEventsByTypes($viewer, $student, $pastoralTypes, $pastoralSubtypes)
            );
        }

        return $output;
    }

    /**
     * return counts of pastoral events types(and subtypes) for a single student
     *
     * @param User            $viewer
     * @param User            $student
     * @param Collection|null $typesWhitelist
     * @param Collection|null $subTypesWhitelist
     * @return Collection
     */
    public function countStudentEventsByTypes(User $viewer, User $student, ?Collection $typesWhitelist = null, ?Collection $subTypesWhitelist = null): Collection
    {
        $pastoralTypes    = $typesWhitelist ?? $this->pastoralTypesWhitelist;
        $pastoralSubtypes = $subTypesWhitelist ?? $this->pastoralSubTypesWhitelist;

        $output = new Collection();

        $studentCounts = new Collection($this->pastoralDataSource->countRecordsByType(
            $viewer,
            $student->getId(),
            $pastoralTypes->getPropertyValues('id'),
            $pastoralSubtypes->getPropertyValues('id')
        ));

        foreach ($studentCounts as $studentCount) {
            $studentCount = new Collection($studentCount);
            /**
             * it's important to derive the colors from subtype before the type - if it exists
             */
            if ($studentCount->offsetExists('subtypeId')) {
                /** @var PastoralSubType|null $subtype */
                $subtype         = $pastoralSubtypes->getOffset($studentCount->getOffset('subtypeId'));
                $foregroundColor = $subtype->getColor();
                $studentCount
                    ->setOffset('icon', $subtype->getIcon())
                    ->setOffset('title', sprintf("%s: %s", $subtype->getPastoralType()->getName(), $subtype->getName()))
                    ->setOffset('subSequence', $subtype->getSequence())
                    ->setOffset('sequence', $subtype->getPastoralType()->getSequence());
            } else {
                /** @var PastoralType|null $type */
                $type            = $pastoralTypes->getOffset($studentCount->getOffset('typeId'));
                $foregroundColor = $type->getColor();
                $studentCount
                    ->setOffset('icon', $type->getIcon())
                    ->setOffset('title', $type->getName())
                    ->setOffset('subSequence', 0)
                    ->setOffset('sequence', $type->getSequence());
            }
            $studentCount
                ->setOffset('bgColor', $this->colorHelper->calculateBackground($foregroundColor)->getAsRgba())
                ->setOffset('fgColor', $foregroundColor->getAsRgba());

            $output->add($studentCount);
        }

        $output->sortByCallback(static function (Collection $a, Collection $b) {
            if ($a['sequence'] === $b['sequence']) {
                return $a['subSequence'] > $b['subSequence'] ? 1 : -1;
            }
            return $a['sequence'] > $b['sequence'] ? 1 : -1;
        });

        return $output;
    }
}
