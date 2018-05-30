<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Alaress\Web\Controller as WebController;
use Schoolbox\Filter;

class Controller extends WebController\Base
{

    /** @var Filter\Filters\Assessment\FactoryFactory */
    private $filterFactoryFactory;

    /** @var Filter\Mapper */
    private $filterMapper;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * @param Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory
     * @param Filter\Mapper                            $filterMapper
     * @param Filter\Selection\Mapper                  $filterSelectionMapper
     */
    public function __construct(
        Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper
    ) {
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
    }


    /**
     * Redirects to the course markbook page with the specified unit pre-selected in filter panel
     *
     * @param UnitSpec\Model $unitSpec
     * @level 18.0
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getUnitMarkbook(UnitSpec\Model $unitSpec): void
    {
        $filter = $this->filterMapper->findByClass(Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class)[0];

        $selections                                       = new GenericCollection(GenericCollection::class);
        $selections[$filter->getId()]                     = $this->filterSelectionMapper->createCollection();
        $selections[$filter->getId()][$unitSpec->getId()] = $this->createSelectionModel($filter, (string)$unitSpec->getId());

        $getArgString = $this->filterFactoryFactory->buildCodec()->convertSelectionsToQueryString($selections);
        $route        = $this->getRouter()->generate('courseMarkbook.getCourseMarkbook', ['courseSpecId' => $unitSpec->getCourse()->getId()]);

        $this->getResponse()->redirect->to("{$route}?{$getArgString}");
    }




    /**
     * Displays the Course Markbook page
     *
     * @param CourseSpec\Model $courseSpec
     * @level 18.0
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getCourseMarkbook(CourseSpec\Model $courseSpec): void
    {
        $queryArgs = $this->getRequest()->query->getArrayCopy();
        unset($queryArgs['page']);
        $this->markbookOuter($courseSpec, $queryArgs);
    }


    /**
     * Renders the outer part of the course markbook page with a filter panel
     *
     * @param CourseSpec\Model $courseSpec
     * @param array            $queryArgs
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function markbookOuter(CourseSpec\Model $courseSpec, array $queryArgs): void
    {
        $page  = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $route = $this->getRouter()->generate('courseMarkbook.postCourseMarkbook', ['userId' => $this->getUser()->getId()]);



        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), $queryArgs);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@courseMarkbook/courseMarkbookOuter.twig', [
                'courseSpec'  => $courseSpec,
                'viewee'      => $this->getUser(),
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle('Course Markbook')
        );
    }


    /**
     * Loads a filtered report into the Course Markbook page
     *
     * @param CourseSpec\Model $courseSpec
     * @level 18.0
     * @todo  Condense this and the GET route into a single route and distinguish via SYNC vs AJAX
     */
    public function postCourseMarkbook(CourseSpec\Model $courseSpec): void
    {
        // TODO implement method
        echo 'function: ' . __FUNCTION__ . "<br>\n";
        echo 'class: ' . \get_class($courseSpec) . "<br>\n";
        echo "id: {$courseSpec->getId()}<br>\n";
        die();
    }


    /**
     * Helper method to create a Filter Selection model
     *
     * @param Filter\Model            $filter
     * @param string                  $key
     * @return Filter\Selection\Model
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createSelectionModel(Filter\Model $filter, string $key): Filter\Selection\Model
    {
        return $this->filterSelectionMapper
            ->createModel()
            ->setFilter($filter)
            ->setUser($this->getUser())
            ->setPage(Filter\Page\Model::PAGE_COURSE_MARK_BOOK)
            ->setKey($key)
            ->setSelected(true);
    }
}
