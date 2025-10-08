<?php

/**
 * Experimental controller for a resource booking category
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Category;

use Alaress\Paginator\IUrlPaginator;
use Alaress\Web\Controller\Base;
use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;
use Schoolbox\External\Event\Mapper;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\ResourceBooking\Report\CalendarApiFactory;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var IUrlPaginator
     */
    protected $paginator;


    /**
     * @var ItemMapper
     */
    private ItemMapper $itemMapper;


    /**
     * Controller constructor.
     *
     * @param ItemMapper $itemMapper
     * @param Mapper     $eventMapper
     */
    public function __construct(ItemMapper $itemMapper, private \Schoolbox\External\Event\Mapper $eventMapper)
    {
        $this->itemMapper = $itemMapper;
    }


    /**
     * Browses the list of categories.
     */
    public function browse()
    {

        $page  = max(intval($this->requestQuery->get('page')), 1);
        $total = $this->mapper->countAll();
        $this->paginator->setTotal($total);

        $list = array();
        if ($total > 0) {
            $list = $this->getMapper()->findAll(
                array(
                    'limit'  => $this->paginator->getPageSize(),
                    'offset' => $this->paginator->getOffset($page),
                    'order'  => ['name' => 'asc',],
                )
            );
        }

        $this->response = $this->responseFromTemplate(
            '@resourceCategory/browse.twig',
            [
                'list'      => $list,
                'paginator' => $this->paginator,
                'page'      => $page,
            ],
        );
    }

    /**
     * Display form for adding another category
     */
    public function add()
    {
        $this->response = $this->responseFromTemplate(
            '@resourceCategory/edit.twig',
            [
                'item' => new Model(),
            ],
        );
    }

    /**
     * Backend for adding a new category
     */
    public function create()
    {
        $post  = $this->requestPost;
        $model = new Model();
        $model->setName($post['name']);
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('resourceCategory.browse', ['format' => ''])
        );
    }

    /**
     * Views an existing category
     *
     * @param Model $category
     */
    public function read(Model $category)
    {
        try {
            $calendarDate = \Alaress_Date_Format::newUrlDate($this->requestQuery->get('date'));
        } catch (\Exception $e) {
            $calendarDate = \Alaress_Date_Format::now();
        }

        $resources = ItemMapper::getInstance()->findByColumn(
            'resource_category_id',
            $category->getId(),
            ['order' => ['name' => 'asc']]
        )->filter(fn($item) => false !== $this->getRouteIfAuthorized("resourceItem.read", ['item' => $item]))
         ->reindex();

        foreach ($resources->getAll() as $resource) {
            /** @var \Schoolbox\ResourceBooking\Item\Model $resource */
            $resource->setBookingIsAuthorized($this->getRouteIfAuthorized('resourceBooking.book', [
                'id' => $resource->getId(),
            ]));
        }

        $this->response = $this->responseFromTemplate(
            '@resourceCategory/read.twig',
            [
                'category'      => $category,
                'resources'     => $resources,
                'calendar' => [
                    'date' => $calendarDate->getDatabase(),
                    'view' => $this->requestQuery->get('view', 'week'),
                ],
                'token' => $this->getUser()->getKey()
            ],
        );
    }

    /**
     * Displays form for editing an existing category
     *
     * @param Model $category
     */
    public function edit(Model $category): void
    {
        $this->response = $this->responseFromTemplate(
            '@resourceCategory/edit.twig',
            [
                'category' => $category,
                'numItems' => $this->itemMapper->findByColumn('resource_category_id', $category->getId())->count()
            ],
        );
    }

    /**
     * Backend for updating an existing category
     *
     * @param Model $category
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function update(Model $category): void
    {
        $post = $this->requestPost;
        $category->setName($post['name']);
        $this->getMapper()->save($category);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('resourceCategory.browse', ['format' => ''])
        );
    }

    /**
     * Backend for deleting a category
     *
     * @param Model $category
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function delete(Model $category): void
    {
        $numItems = $this->itemMapper->findByColumn('resource_category_id', $category->getId())->count();

        /* Don't delete if there are items associated
         */
        if (!$numItems) {
            $this->getMapper()->delete($category);
        }

        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('resourceCategory.browse', ['format' => ''])
        );
    }

    /**
     * Action for outputting a calendar
     *
     * @param Model $category
     * @throws \Exception
     */
    public function calendar(Model $category)
    {
        $apiFactory = new CalendarApiFactory($this->getUser(), new \External_Timetable(), $this->eventMapper);
        $source     = $apiFactory->buildFromResourceCollection(
            ItemMapper::getInstance()->findByColumn(
                'resource_category_id',
                $category->getId(),
                ['order' => ['name' => 'asc',]]
            ),
            true,
            $this->requestQuery['status']
        );
        // put the results into a renderer
        $calendar = new Renderer(
            $source,
            (int)$this->requestQuery->get('start'),
            (int)$this->requestQuery->get('end'),
            $this->getUser(),
            $this->getAuthorizer(),
            $this->routeGenerator,
            $this->getUser()
        );
        $output = json_decode('[' . \implode(',', $calendar->renderAll(EventBase::RENDER_FORMAT_JSON)) . ']');
        $this->response = $this->responseAsJson($output);
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param  IUrlPaginator $paginator
     * @return $this
     */
    public function setPaginator($paginator)
    {
        $this->paginator = $paginator;
        return $this;
    }

    /**
     * @return IUrlPaginator
     */
    public function getPaginator()
    {
        return $this->paginator;
    }
}
