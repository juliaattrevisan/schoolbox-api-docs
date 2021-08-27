<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Count;

use Alaress\Color\Color;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Audience\ColorHelper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\User;
use Alaress_Date_Format;

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
     * @var Collection|PastoralType[]|null
     */
    private $pastoralTypesWhitelist;

    /**
     * @var Collection|PastoralSubType[]|null
     */
    private $pastoralSubTypesWhitelist;

    private Router $router;

    /**
     * helper to bundle similar counts related to pastoral events of students
     *
     * @param Router                $router
     * @param PastoralTypeMapper    $pastoralTypeMapper
     * @param PastoralSubTypeMapper $pastoralSubTypeMapper
     * @param PastoralDataSource    $pastoralDataSource
     */
    public function __construct(Router $router, PastoralTypeMapper $pastoralTypeMapper, PastoralSubTypeMapper $pastoralSubTypeMapper, PastoralDataSource $pastoralDataSource)
    {
        $this->pastoralTypeMapper    = $pastoralTypeMapper;
        $this->pastoralSubTypeMapper = $pastoralSubTypeMapper;
        $this->pastoralDataSource    = $pastoralDataSource;
        $this->router                = $router;
        $this->colorHelper           = new ColorHelper();
    }

    /**
     * initialise fallback whitelist of types and subtypes to use
     */
    private function initTypeWhitelists(bool $allowWithoutIcons = false)
    {
        /**
         * we only need to run this once
         */
        if ($this->pastoralTypesWhitelist && $this->pastoralSubTypesWhitelist) {
            return;
        }

        $pastoralTypePredicate    = $this->pastoralTypeMapper->getPredicateBuilder();
        $pastoralSubTypePredicate = $this->pastoralSubTypeMapper->getPredicateBuilder();
        /**
         * do we want types that have colors/icons
         */
        if ($allowWithoutIcons === false) {
            $pastoralTypePredicate->notNull('color')->notNull('icon');
            $pastoralSubTypePredicate->notNull('color')->notNull('icon');
        }
        $this->pastoralTypesWhitelist    = $this->pastoralTypeMapper->findByPredicates($pastoralTypePredicate);
        $this->pastoralSubTypesWhitelist = $this->pastoralSubTypeMapper->findByPredicates($pastoralSubTypePredicate);
    }

    /**
     * return counts of pastoral events types(and subtypes) for multiple students
     *
     * @param User            $viewer            the logged-in user that would like to inspect this information
     * @param Collection      $students          collection of students we want to count the pastoral events
     * @param Collection|null $typesWhitelist    optional whitelist of pastoral types that should be counted
     * @param Collection|null $subTypesWhitelist optional whitelist of pastoral subtypes that should be counted
     * @return Collection
     * @throws RouteNotFound
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
     * @param bool            $partitionByMonth
     * @return Collection
     * @throws RouteNotFound
     */
    public function countStudentEventsByTypes(User $viewer, User $student, ?Collection $typesWhitelist = null, ?Collection $subTypesWhitelist = null, bool $partitionByMonth = false): Collection
    {
        /**
         * if we're partitioned by month, then we will want to make sure that we are fetching all types & subtypes,
         * regardless of whether they have icon and colors attached to them
         */
        $this->initTypeWhitelists();

        $pastoralTypes    = $typesWhitelist ?? $this->pastoralTypesWhitelist;
        $pastoralSubtypes = $subTypesWhitelist ?? $this->pastoralSubTypesWhitelist;


        $studentCounts = new Collection(
            $this->pastoralDataSource->countRecordsByType(
                $viewer,
                $student->getId(),
                $pastoralTypes->getPropertyValues('id'),
                $pastoralSubtypes->getPropertyValues('id'),
            )
        );

        $output = $this->decorateCounts($studentCounts, $pastoralSubtypes, $pastoralTypes, $student);

        return $output;
    }

    /**
     * returns counts of pastoral evens types(and subtypes) for a single student but additionally partitioned by the
     * month and year they were created in
     *
     * @param User            $viewer
     * @param User            $student
     * @return Collection
     * @throws RouteNotFound
     */
    public function countStudentEventsByTypesByMonth(User $viewer, User $student): Collection
    {
        /**
         * if we're partitioned by month, then we will want to make sure that we are fetching all types & subtypes,
         * regardless of whether they have icon and colors attached to them
         */
        $this->initTypeWhitelists(true);

        $studentCounts = new Collection(
            $this->pastoralDataSource->countRecordsByTypeGroupByMonth(
                $viewer,
                $student->getId()
            )
        );

        return $this->decorateCounts($studentCounts, $this->pastoralSubTypesWhitelist, $this->pastoralTypesWhitelist, $student);
    }

    /**
     * build the orthogonal datasets that a stacked barchart needs: categories and series
     *
     * @param Collection $data
     * @return array
     */
    public function buildMonthlyBarChart(Collection $data): array
    {
        /**
         * a unique array of type and subtypes that contain counts for a month, in a year
         */
        $series = [];

        foreach ($data as $row) {
            /**
             * save this pastoral type & subtype into the series and
             * take a copy of the preferred color
             */
            $uniqueSeriesID = $row['typeId'] . '-' . $row['subtypeId'];

            $series[$uniqueSeriesID]['name']  = $row['title'];
            $series[$uniqueSeriesID]['color'] = $row['fgColor'];
            $series[$uniqueSeriesID]['url']   = $row['destinationUrl'];

            $summaryMonthLabel = (new Alaress_Date_Format())
                ->setDay(1)
                ->setMonth($row['partition_month'])
                ->setYear($row['partition_year'])
                ->startOfMonth()->getDateTimeObject()->valueOf();

            $series[$uniqueSeriesID]['data'][] = [$summaryMonthLabel, (int)$row['count']];
        }

        return array_values($series);
    }

    /**
     * @param Collection $studentCounts
     * @param Collection $pastoralSubtypes
     * @param Collection $pastoralTypes
     * @param User       $student
     * @return Collection
     * @throws RouteNotFound
     */
    private function decorateCounts(Collection $studentCounts, Collection $pastoralSubtypes, Collection $pastoralTypes, User $student): Collection
    {
        $output = new Collection();

        foreach ($studentCounts as $studentCount) {
            $studentCount = new Collection($studentCount);
            /**
             * it's important to derive the colors from subtype before the type - if it exists
             */
            $studentSubTypeId = $studentCount->getOffset('subtypeId');
            $studentTypeId    = $studentCount->getOffset('typeId');

            /**
             * handle the rare cases where a pastoral event has a subtype, but no icon and color
             */
            if ($studentCount->offsetExists('subtypeId') && !$pastoralSubtypes->offsetExists($studentSubTypeId)) {
                continue;
            }

            /**
             * checking whether the subtype is within the whitelist is important, because it's possible to assign a
             * subtype after its been created and assigned.
             */
            if ($studentCount->offsetExists('subtypeId')) {
                /** @var PastoralSubType|null $subtype */
                $subtype         = $pastoralSubtypes->getOffset($studentSubTypeId);
                $foregroundColor = $subtype->getColor();
                $studentCount
                    ->setOffset('icon', $subtype->getIcon())
                    ->setOffset('title', sprintf("%s: %s", $subtype->getPastoralType()->getName(), $subtype->getName()))
                    ->setOffset('subSequence', $subtype->getSequence())
                    ->setOffset('sequence', $subtype->getPastoralType()->getSequence());
            } else {
                /** @var PastoralType|null $type */
                $type            = $pastoralTypes->getOffset($studentTypeId);
                $foregroundColor = $type->getColor();
                $studentCount
                    ->setOffset('icon', $type->getIcon())
                    ->setOffset('title', $type->getName())
                    ->setOffset('subSequence', 0)
                    ->setOffset('sequence', $type->getSequence());
            }
            /** @noinspection PhpUnhandledExceptionInspection */
            $destinationUrl = sprintf(
                "%s?type=%s&subtype=%s",
                $this->router->generate('pastoral.getStudent', ['studentId' => $student->getId()]),
                $studentCount->getOffset('typeId'),
                $studentCount->getOffset('subtypeId')
            );
            $studentCount->setOffset('destinationUrl', $destinationUrl);

            /**
             * not all the types and subtypes will have a color provided
             */
            if ($foregroundColor instanceof Color) {
                $studentCount->setOffset('fgColor', $foregroundColor->getAsRgba())
                             ->setOffset('bgColor', $this->colorHelper->calculateBackground($foregroundColor)->getAsRgba());
            }

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
