<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Filter;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
use UnderflowException;
use UnexpectedValueException;

class Controller extends Base
{
    /** @var Filter\Filters\Assessment\FactoryFactory */
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
     * Controller constructor.
     *
     * @param Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory
     * @param RosettaStone                             $rosettaStone
     * @param TermMapper                               $termMapper
     */
    public function __construct(
        Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory,
        RosettaStone $rosettaStone,
        TermMapper $termMapper
    ) {
        $this->filterFactoryFactory = $filterFactoryFactory;
        $this->rosettaStone         = $rosettaStone;
        $this->termMapper           = $termMapper;
    }

    /**
     * Displays the synchronous portion of the Assessment Calendar page.
     *
     * @throws DefinitionException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws RouteNotFound
     * @throws ServiceNotFound
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @api   GET /learning/assessment/calendar
     * @since 19.1
     */
    public function getReport(): void
    {
        $pageTitle = sprintf('%s Calendar', $this->rosettaStone->translate('assessment', false, true));

        $queryArgs = $this->request->query->getArrayCopy();
        unset($queryArgs['page']);

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
                    'filterPanel' => $filterPanel,
                    'termDates'   => $this->getAvailableTermDates(),
                    'eventTypes'  => EventTypeMapper::getInstance()->findAll(),
                ],
                'Assessment Calendar',
                $pageTitle
            )
        );
    }

    /**
     * @return array
     */
    private function getAvailableTermDates(): array
    {
        $termDates['past'] = [];
        $termDates['future'] = [];

        $terms = $this->termMapper->findAll()->sortBy('start', 'DESC');

        $currentTermStartDate = $this->getCurrentTermDates()->getStart()->getDatabaseDate();

        /** @var TermMapper $term */
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
     * @return \Schoolbox\Assessment\Term\Model
     */
    private function getCurrentTermDates(): \Schoolbox\Assessment\Term\Model
    {
        return $this->termMapper->findForDate(\Alaress_Date_Format::now());
    }
}
