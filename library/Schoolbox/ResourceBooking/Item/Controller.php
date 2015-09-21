<?php
/**
 * Controller for resource items.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use DateTime;
use Alaress\Paginator\IUrlPaginator;
use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Storage;

class Controller extends Base
{

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var IUrlPaginator
     */
    protected $paginator;

    /**
     * Browses the list of items.
     */
    public function browse()
    {

        // Get the list of items
        $query  = array();
        $catId  = $this->getRequest()->query['category'];
        $search = trim($this->getRequest()->query['search']);
        $page   = max(intval($this->getRequest()->query['page']), 1);

        // Use the paginator to limit searches
        $this->paginator->setUrl($this->request->url->get());
        $list = array();
        if (strlen($search) > 0) {
            $total = $this->mapper->searchCount($search);
            $this->paginator->setTotal($total);
            if ($total > 0) {
                $list = $this->getMapper()->search(
                    $search,
                    $this->paginator->getOffset($page),
                    $this->paginator->getPageSize(),
                    'name',
                    'asc'
                );
            }
        } else {
            if ($catId) {
                $query['resource_category_id'] = $catId;
                try {
                    $category = $this->getMapper()->getJoinMapper('category')->findById($catId);
                } catch (\Exception_Schoolbox_NotFound $e) {
                    $this->getResponse()->status->setCode(404);
                }
            }
            else {
                $catId = '';
                $category = $this->getMapper()->getJoinMapper('category')->createModel();
                $category->setName('All');
            }

            // Get count before running list
            $total = $this->mapper->countByColumnList($query);
            $this->paginator->setTotal($total);
            if ($total > 0) {
                $list = $this->mapper->findByColumnList(
                    $query,
                    array(
                        'order'  => array('name' => 'asc'),
                        'limit'  => $this->paginator->getPageSize(),
                        'offset' => $this->paginator->getOffset($page),
                    )
                );
            }
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/browse.twig',
                'data'     => array(
                    'list'         => $list,
                    'categoryId'   => $catId,
                    'category'     => $category,
                    'categoryList' => $this->getMapper()->getJoinMapper('category')->findAll(),
                    'search'       => $search,
                    'paginator'    => $this->paginator,
                    'page'         => $page,
                ),
            )
        );
    }

    /**
     * JSON search for resources
     *
     * @todo remove and integrate into browse() once BelgianSuggest is gone
     */
    public function search()
    {
        $excludedId = $this->getRequest()->query['thisId'];
        $search     = trim($this->getRequest()->query['query']);
        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => $this->getMapper()->search($search)
                    ->filter(function($item) use ($excludedId) {
                        return $item->getId() != $excludedId;
                    })
                    ->map(function($item) {
                        return array(
                            'id'   => $item->getId(),
                            'name' => $item->getName(),
                        );
                    }),
            )
        );
    }

    /**
     * Display form for adding another category
     */
    public function add()
    {
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/edit.twig',
                'data'     => array(
                    'item'            => new Model(),
                    'categoryList'    => $this->getMapper()->getJoinMapper('category')->findAll(),
                    'moderationList'  => $this->getMapper()->getJoinMapper('moderation_type')->findAll(),
                    'moderatorList'   => array(),
                    'relatedItemList' => array(),
                    'roleList'        => $this->roleMapper->findAll(),
                ),
            )
        );
    }

    /**
     * Views an existing category
     *
     * @param Model $item
     */
    public function read(Model $item)
    {
        // Show a given view and date
        $calendarDate = DateTime::createFromFormat('d-m-Y', $this->request->query['date']);
        if (!($calendarDate instanceof DateTime)) {
            $calendarDate = new DateTime('now');
        }
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/read.twig',
                'data'     => array(
                    'item'     => $item,
                    'calendar' => array(
                        'date' => $calendarDate->format('Y-m-d'),
                        'view' => $this->request->query['view'] ?: 'week',
                    )
                ),
            )
        );
    }

    /**
     * Display form for editing a category
     *
     * @param Model $item
     */
    public function edit(Model $item)
    {
        // Build list of moderators and related items (for BelgianSuggest)
        $moderatorList = array();
        foreach ($item->getModeratorList() as $moderator) {
            $moderatorList[] = array(
                'id'   => $moderator->getId(),
                'name' => $moderator->formatName(),
            );
        }
        $relatedItemList = array();
        foreach ($item->getRelatedResourceList() as $relatedItem) {
            $relatedItemList[] = array(
                'id'   => $relatedItem->getId(),
                'name' => $relatedItem->getName(),
            );
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/edit.twig',
                'data'     => array(
                    'item'            => $item,
                    'categoryList'    => $this->getMapper()->getJoinMapper('category')->findAll(),
                    'moderationList'  => $this->getMapper()->getJoinMapper('moderation_type')->findAll(),
                    'moderatorList'   => $moderatorList,
                    'relatedItemList' => $relatedItemList,
                    'roleList'        => $this->roleMapper->findAll(),
                ),
            )
        );
    }

    /**
     * Backend for adding a new category
     */
    public function create()
    {
        $model = new Model();
        $this->updateItemWithFormValues(
            $model,
            $this->getRequest()->post,
            $this->getRequest()->files
        );
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.browse', array())
        );
    }

    /**
     * Backend for updating an existing category
     *
     * @param Model $item
     */
    public function update(Model $item)
    {
        $this->updateItemWithFormValues(
            $item,
            $this->getRequest()->post,
            $this->getRequest()->files
        );
        $this->getMapper()->save($item);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.browse', array())
        );
    }

    /**
     * Deletes an item
     *
     * @param Model $item
     */
    public function delete(Model $item)
    {
        $this->getMapper()->delete($item);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.browse', array())
        );
    }

    /**
     * Updates fields in the model with values from the form.
     *
     * @param Model $model The resource to update
     * @param array $post  Data from $_POST
     * @param array $files Data from $_FILES
     */
    protected function updateItemWithFormValues(Model $model, $post, $files)
    {
        $model
            ->setName($post['name'])
            ->setRoomCode($post['room_code'])
            ->setDescription($post['description'])
            ->setCategory($post['resource_category_id'])
            ->setModerationType($post['resource_moderation_type_id'])
            ->setMinLeadTime($this->getDateIntervalFromForm($post['min_lead_time']))
            ->setMinReservationTime($this->getDateIntervalFromForm($post['min_reservation_time']))
            ->setMaxReservationTime($this->getDateIntervalFromForm($post['max_reservation_time']))
        ;
        if (($upload = $files['image']) && empty($upload['error'])) {
            // Rectumize the file that was uploaded
            $image = Storage\Mapper::getInstance()->accept(
                $upload['name'], $upload['tmp_name'], $this->getUser()->getId()
            );
            $model->setImage($image);
        } else {
            // Get the file out of the rectum, and put it in the resource
            if ($post['image']) {
                try {
                    $model->setImage(Storage\Mapper::getInstance()->findByHash($post['image']));
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $model->setImage(null);
                }
            } else {
                $model->setImage(null);
            }
        }

        // Clear and create the moderator list
        $model->setModeratorList(new Collection());
        // because of BelgianSuggest I have to check if this is even an array!
        if (is_array($post['moderator_id'])) {
            foreach ($post['moderator_id'] as $moderator_id) {
                $user = new User();
                $user->setId($moderator_id);
                $model->addModerator($user);
            }
        }

        // Do likewise with the related resource list
        $model->setRelatedResourceList(new Collection());
        if (is_array($post['related_resource_id'])) {
            foreach ($post['related_resource_id'] as $related_resource_id) {
                $resource = new Model();
                $resource->setId($related_resource_id);
                $model->addRelatedResource($resource);
            }
        }

        // Do likewise with the role list
        $model->setRoleList(new Collection());
        if (is_array($post['role'])) {
            foreach ($post['role'] as $roleId => $allowed) {
                $resourceRole = new RolePermission\Model();
                $role         = new \Schoolbox\Role();
                $role->setId($roleId);
                $resourceRole
                    ->setResource($model)
                    ->setRole($role)
                    ->setAllowed($allowed);
                $model->addRole($resourceRole);
            }
        }

    }

    /**
     * Retrieves a date interval from form data
     *
     * @param  array $formPart An array containing "day", "hour" and "minute"
     *                         keys.
     * @return \DateInterval
     * @throws \Exception if the date interval object can't be created.
     */
    protected function getDateIntervalFromForm($formPart)
    {
        return new \DateInterval(
            sprintf('PT%dH%dM', ($formPart['day'] * 24 + $formPart['hour']), $formPart['minute'])
        );
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
     * @param  UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  RoleMapper $roleMapper
     * @return $this
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
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
