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
     * Controller constructor.
     *
     * @param Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory
     * @param RosettaStone                             $rosettaStone
     */
    public function __construct(
        Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory,
        RosettaStone $rosettaStone
    ) {
        $this->filterFactoryFactory = $filterFactoryFactory;
        $this->rosettaStone         = $rosettaStone;
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

        $page        = Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR;
//        $route       = $this->getRouter()->generate('learningAssessmentCalendar.postReport');
        // This route goes nowhere: all is contained on the FullCalendar
        $route       = '#';
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), [], $queryArgs);

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessmentCalendar/index.twig',
                ['filterPanel' => $filterPanel,],
                'submission',
                $pageTitle
            )
        );
    }

//    /**
//     * Returns the contents of the Assessment Calendar page
//     *
//     * @api   POST /learning/assessment/calendar
//     * @since 19.1
//     */
//    public function postReport(): void
//    {
//        // Might not even need to do anything here.
//        $eventData =
//            "TODO - calendar event JSON data <br>\n" .
//            print_r($this->request->post->getArrayCopy(), true);
//
//        $this->response->content->set($this->buildJsonOutput([
//            'status'  => 200,
//            'content' => $eventData,
//        ]));
//    }
}
