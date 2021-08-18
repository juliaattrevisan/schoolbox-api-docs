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

        /**
         * ensure we arent displaying deleted types
         */
        $pastoralTypePredicate    = $this->pastoralTypeMapper->getPredicateBuilder()->isNull('deleted_at');
        $pastoralSubTypePredicate = $this->pastoralSubTypeMapper->getPredicateBuilder()->isNull('deleted_at');
        /**
         * but do we want types that have colors/icons
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
        $this->initTypeWhitelists($partitionByMonth);

        $pastoralTypes    = $typesWhitelist ?? $this->pastoralTypesWhitelist;
        $pastoralSubtypes = $subTypesWhitelist ?? $this->pastoralSubTypesWhitelist;

        $output = new Collection();

        $studentCounts = new Collection(
            $this->pastoralDataSource->countRecordsByType(
                $viewer,
                $student->getId(),
                $pastoralTypes->getPropertyValues('id'),
                $pastoralSubtypes->getPropertyValues('id'),
                $partitionByMonth
            )
        );

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

            if ($partitionByMonth === true) {
                $summaryMonthLabel = (new Alaress_Date_Format())
                    ->setDay(1)
                    ->setMonth($studentCount->getOffset('partition_month'))
                    ->setYear($studentCount->getOffset('partition_year'))
                    ->getShortMonthYear();

                $studentCount->setOffset('partition_label', $summaryMonthLabel);
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

    /**
     * returns counts of pastoral evens types(and subtypes) for a single student but additionally partitioned by the
     * month and year they were created in
     *
     * @param User            $viewer
     * @param User            $student
     * @param Collection|null $typesWhitelist
     * @param Collection|null $subTypesWhitelist
     * @return Collection
     * @throws RouteNotFound
     */
    public function countStudentEventsByTypesByMonth(User $viewer, User $student, ?Collection $typesWhitelist = null, ?Collection $subTypesWhitelist = null): Collection
    {
        return $this->countStudentEventsByTypes($viewer, $student, $typesWhitelist, $subTypesWhitelist, true);
    }

    /**
     * build the orthogonal datasets that a stacked barchart needs: categories and series
     *
     * @param Collection $data
     * @return array
     */
    public function buildMonthlyBarChart(Collection $data): array
    {
        $fieldName = [
            'series'   => 'title',
            'category' => 'partition_label',
            'count'    => 'count',
            'color'    => 'fgColor',
            'url'      => 'destinationUrl',
        ];

        /**
         * a unique array of months and the year
         */
        $categories = [];
        /**
         * a unique array of type and subtypes that contain counts for a month, in a year
         */
        $series = [];

        foreach ($data as $row) {
            /**
             * extract any month & years as categories in lookup table
             */
            $categories[$row[$fieldName['category']]] = true;
            /**
             * save this pastoral type & subtype into the series and
             * take a copy of the preferred color
             */
            if (!isset($series[$row[$fieldName['series']]])) {
                $series[$row[$fieldName['series']]] = [
                    'name'  => $row[$fieldName['series']],
                    'color' => $row['fgColor'],
                    'url'   => $row['destinationUrl'],
                    'data'  => [],
                ];
            }
            /**
             * finally, make sure we capture the number of events in this type + subtype + month + year
             */
            $series[$row[$fieldName['series']]]['data'][$row[$fieldName['category']]] = (int)$row[$fieldName['count']];
        }
        /**
         * flip the category lookup, so we have a unique list of month + years
         */
        $categories = array_keys($categories);

        /**
         * go back over the series and fill in any that had no counts
         */
        foreach ($series as &$row) {
            if (count($row['data']) < count($categories)) {
                foreach ($categories as $category) {
                    $row['data'][$category] ??= 0;
                }
            }
            /**
             * strip out the lookup index
             */
            $row['data'] = array_values($row['data']);
        }

        return [$categories, array_values($series)];
    }
}
