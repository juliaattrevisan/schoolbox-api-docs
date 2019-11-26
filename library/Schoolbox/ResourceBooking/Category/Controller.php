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
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\ResourceBooking\Report\CalendarApiFactory;

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var IUrlPaginator
     */
    protected $paginator;

    /**
     * Browses the list of categories.
     */
    public function browse() {

        $page  = max(intval($this->getRequest()->query['page']), 1);
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

        $response = $this->getResponse();
        $response->content->set(
            array(
                'template' => '@resourceCategory/browse.twig',
                'data'     => array(
                    'list'      => $list,
                    'paginator' => $this->paginator,
                    'page'      => $page,
                ),
            )
        );
    }

    /**
     * Display form for adding another category
     */
    public function add() {
        $response = $this->getResponse();
        $response->content->set(
            array(
                'template' => '@resourceCategory/edit.twig',
                'data'     => array(
                    'item' => new Model(),
                ),
            )
        );
    }

    /**
     * Backend for adding a new category
     */
    public function create() {
        $post  = $this->getRequest()->post;
        $model = new Model();
        $model->setName($post['name']);
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceCategory.browse', array())
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
            $calendarDate = \Alaress_Date_Format::newUrlDate($this->request->query->get('date'));
        } catch (\Exception $e) {
            $calendarDate = \Alaress_Date_Format::now();
        }


        $resources = ItemMapper::getInstance()->findByColumn(
            'resource_category_id',
            $category->getId(),
            ['order' => ['name' => 'asc']]
        );

        foreach ($resources->getAll() as $resource) {
            /** @var \Schoolbox\ResourceBooking\Item\Model $resource */
            $resource->setBookingIsAuthorized($this->getRouteIfAuthorized('resourceBooking.book', [
                'id' => $resource->getId(),
            ]));
        }

        $this->getResponse()->content->set(
            [
                'template' => '@resourceCategory/read.twig',
                'data'     => [
                    'category'      => $category,
                    'resources' => ItemMapper::getInstance()->findByColumn(
                        'resource_category_id',
                        $category->getId(),
                        ['order' => ['name' => 'asc']]
                    ),
                    'calendar' => [
                        'date' => $calendarDate->getDatabase(),
                        'view' => $this->request->query->get('view', 'week'),
                    ],
                    'token' => $_SESSION['key']
                ],
            ]
        );
    }

    /**
     * Displays form for editing an existing category
     *
     * @param Model $item
     */
    public function edit(Model $item) {
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceCategory/edit.twig',
                'data'     => array(
                    'item' => $item,
                ),
            )
        );
    }

    /**
     * Backend for updating an existing category
     *
     * @param Model $item
     */
    public function update(Model $item) {
        $post = $this->getRequest()->post;
        $item->setName($post['name']);
        $this->getMapper()->save($item);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceCategory.browse', array())
        );
    }

    /**
     * Backend for deleting a category
     *
     * @param Model $item
     */
    public function delete(Model $item) {
        $this->getMapper()->delete($item);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceCategory.browse', array())
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
        $apiFactory = new CalendarApiFactory($this->getUser(), new \External_Timetable());
        $source     = $apiFactory->buildFromResourceCollection(
            ItemMapper::getInstance()->findByColumn(
                'resource_category_id',
                $category->getId(),
                ['order' => ['name' => 'asc',]]
            ),
            true,
            $this->getRequest()->query['status']
        );
        // put the results into a renderer
        $calendar = new Renderer(
            $source,
            (int)$this->getRequest()->query->get('start'),
            (int)$this->getRequest()->query->get('end'),
            $this->getUser(),
            $this->getDi(),
            $this->getUser()
        );
        $output = json_decode('[' . \implode(',', $calendar->renderAll(EventBase::RENDER_FORMAT_JSON)) . ']');
        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => $output,
            )
        );
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper) {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper() {
        return $this->mapper;
    }

    /**
     * @param  IUrlPaginator $paginator
     * @return $this
     */
    public function setPaginator($paginator) {
        $this->paginator = $paginator;
        return $this;
    }

    /**
     * @return IUrlPaginator
     */
    public function getPaginator() {
        return $this->paginator;
    }

}
