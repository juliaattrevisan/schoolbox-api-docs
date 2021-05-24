<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\Readable\Definition as FolderReadableDefinition;
use Schoolbox\Filter\Filters\Assessment\NullableContext\CourseImportSpec\Definition as CourseImportSpecDefinition;
use Schoolbox\Filter\Mapper as FilterMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper;

class Controller extends Base
{
    /**
     * @var Filter\IFactoryFactory
     */
    private $filterFactoryFactory;

    /**
     * @var RosettaStone
     */
    private $rosettaStone;

    /**
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @var EventTypeMapper
     */
    private $eventTypeMapper;

    /**
     * @var FilterMapper
     */
    private $filterMapper;

    /**
     * Controller constructor.
     *
     * @param Filter\IFactoryFactory $filterFactoryFactory
     * @param RosettaStone           $rosettaStone
     * @param TermMapper             $termMapper
     * @param EventTypeMapper        $eventTypeMapper
     * @param FilterMapper           $filterMapper
     */
    public function __construct(
        Filter\IFactoryFactory $filterFactoryFactory,
        RosettaStone $rosettaStone,
        TermMapper $termMapper,
        EventTypeMapper $eventTypeMapper,
        FilterMapper $filterMapper
    ) {
        $this->filterFactoryFactory = $filterFactoryFactory;
        $this->rosettaStone         = $rosettaStone;
        $this->termMapper           = $termMapper;
        $this->eventTypeMapper      = $eventTypeMapper;
        $this->filterMapper         = $filterMapper;
    }

    /**
     * Displays the synchronous portion of the Assessment Calendar page.
     *
     * @throws Mapper\Exception
     * @throws ServiceNotFound
     * @api   GET /learning/assessment/calendar
     * @since 19.1
     */
    public function getReport(): void
    {
        $pageTitle = sprintf('%s Calendar', $this->rosettaStone->translate('assessment', false, true));

        $queryArgs = $this->request->query->getArrayCopy();
        unset($queryArgs['page']);

        $inContext = false;
        // If course/folder id is provided, filter to the course/folder
        if (is_numeric($queryArgs['course']) || is_numeric($queryArgs['folder'])) {
            $filterType = is_numeric($queryArgs['course']) ? 'course' : 'folder';
            $queryArgs  = $this->applyFilter($filterType, (int)$queryArgs[$filterType]);
            $inContext  = true;
            unset($queryArgs[$filterType]);
        }

        // This filter panel does not post anywhere when its filters change.
        // Any on-change action is handled by the full calendar component.
        $page        = Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR;
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            $page,
            null,
            $this->getUser(),
            [],
            $queryArgs
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessmentCalendar/index.twig',
                [
                    'user'        => $this->getUser(),
                    'filterPanel' => $filterPanel,
                    'termDates'   => $this->getAvailableTermDates(),
                    'eventTypes'  => $this->eventTypeMapper->findAll(),
                    'inContext'   =>  $inContext,
                ],
                'Assessment Calendar',
                $pageTitle
            )
        );
    }

    /**
     * Apply single course / folder filter provided
     * @param string $filterType
     * @param int    $filterValue
     * @return array
     */
    private function applyFilter(string $filterType, int $filterValue): array
    {
        $filterTypesToApply = [
          'folder' =>  FolderReadableDefinition::class,
          'course' => CourseImportSpecDefinition::class,
        ];
        $queryArgs = [];
        $filters   = $this->filterMapper->findForEntity(AssessmentMapper::class);
        foreach ($filters as $filter) {
            if (!in_array($filter->getClass(), $filterTypesToApply)) {
                continue;
            }
            if ($filterTypesToApply[$filterType] === $filter->getClass()) {
                $queryArgs[$filter->getId()][$filterValue] = 1;
            } else {
                $queryArgs[$filter->getId()] = [];
            }
        }
        return $queryArgs;
    }

    /**
     * @return array
     * @throws Mapper\Exception
     */
    private function getAvailableTermDates(): array
    {
        $termDates['past'] = [];
        $termDates['future'] = [];

        $terms = $this->termMapper->findByColumn('archived', false)->sortBy('start', 'DESC');

        $currentTermStartDate = $this->getCurrentTermDates()->getStart()->getDatabaseDate();

        /** @var Term $term */
        foreach ($terms as $term) {
            if ($term->getStart()->getDatabaseDate() < $currentTermStartDate) {
                $termDates['past'][] = [
                    'start' => $term->getStart()->getDatabaseDate(),
                    'end'   => $term->getDisplayFinish()->getDatabaseDate(),
                ];
            } elseif ($term->getStart()->getDatabaseDate() > $currentTermStartDate) {
                $termDates['future'][] = [
                    'start' => $term->getStart()->getDatabaseDate(),
                    'end'   => $term->getDisplayFinish()->getDatabaseDate(),
                ];
            }
        }

        return array_merge(
            [
                'past' => $termDates['past']
            ],
            [
                'current' => [
                    'start' => $currentTermStartDate,
                    'end' => $this->getCurrentTermDates()->getDisplayFinish()->getDatabaseDate(),
                ]
            ],
            [
                'future' => $termDates['future']
            ]
        );
    }

    /**
     * @return Term
     * @throws Mapper\Exception
     */
    private function getCurrentTermDates(): Term
    {
        return $this->termMapper->findForDate(\Alaress_Date_Format::now());
    }
}
