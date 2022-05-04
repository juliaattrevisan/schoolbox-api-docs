<?php

/**
 * Controller for resource items.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use Aura\Router\Exception\RouteNotFound;
use Aura\Web\Exception\InvalidComponent;
use Aura\Web\Exception\InvalidStatusCode;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use DateTime;
use Alaress\Paginator\IUrlPaginator;
use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;

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

    /** @var TermMapper */

    private $termMapper;

    /**
     * @var IUrlPaginator
     */
    protected $paginator;

    /**
     * Browses the list of items.
     * @throws InvalidComponent
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws InvalidStatusCode
     */
    public function browse(): void
    {

        // Get the list of items
        $query    = [];
        $catId    = $this->getRequest()->query->get('category');
        $search   = trim($this->getRequest()->query->get('search'));
        $page     = max(intval($this->getRequest()->query->get('page')), 1);
        $category = $this->mapper->getJoinMapper('category')->createModel();
        $category->setName('All');

        // Use the paginator to limit searches
        $this->paginator->setUrl($this->request->url->get());
        $list = $this->mapper->createCollection();
        if (strlen($search) > 0) {
            $total = $this->mapper->searchCount($search);
            $this->paginator->setTotal($total);
            if ($total > 0) {
                $list = $this->mapper->search(
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
                    $category = $this->mapper->getJoinMapper('category')->findById($catId);
                } catch (SchoolboxNotFoundException $e) {
                    $this->getResponse()->status->setCode(404);
                }
            }

            // Get count before running list
            $total = $this->mapper->countByColumnList($query);
            $this->paginator->setTotal($total);
            if ($total > 0) {
                $list = $this->mapper->findByColumnList(
                    $query,
                    [
                        'order'  => ['name' => 'asc'],
                        'limit'  => $this->paginator->getPageSize(),
                        'offset' => $this->paginator->getOffset($page),
                    ]
                );
            }
        }

        $this->getResponse()->content->set(
            [
                'template' => '@resourceItem/browse.twig',
                'data'     => [
                    'list'         => $list->sortByCallback(static function (Model $a, Model $b) {
                        return strnatcasecmp($a->getName(), $b->getName());
                    }),
                    'categoryId'   => $catId,
                    'category'     => $category,
                    'categoryList' => $this->mapper->getJoinMapper('category')->findAll([
                        'order' => ['name' => 'asc',],
                    ]),
                    'search'       => $search,
                    'paginator'    => $this->paginator,
                    'page'         => $page,
                ],
            ]
        );
    }

    /**
     * JSON search for resources
     *
     * @todo remove and integrate into browse() once BelgianSuggest is gone
     */
    public function search(): void
    {
        $excludedId = $this->getRequest()->query->get('thisId');
        $search     = trim($this->getRequest()->query->get('query'));
        $data       = $this->mapper->search($search)
                           ->filter(function (Model $item) use ($excludedId) {
                               return $item->getId() != $excludedId;
                           })
                           ->map(function (Model $item) {
                               return [
                                   'id'   => $item->getId(),
                                   'name' => $item->getName(),
                               ];
                           });
        $data->reindex();
        $this->getResponse()->content->set(
            [
                'format' => 'json',
                'data'   => $data,
            ]
        );
    }

    /**
     * Display form for adding another resource item
     * @throws MapperException
     */
    public function add(): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@resourceItem/edit.twig',
                'data'     => [
                    'item'            => new Model(),
                    'categoryList'    => $this->mapper->getJoinMapper('category')->findAll([
                        'order' => ['name' => 'asc',],
                    ]),
                    'moderationList'  => $this->mapper->getJoinMapper('moderation_type')->findAll(),
                    'moderatorList'   => [],
                    'relatedItemList' => [],
                    'roleList'        => $this->roleMapper->findAll(),
                    'attachzone'      => $this->getAttachZoneData($this->mapper->createModel()),
                ],
            ]
        );
    }

    /**
     * Views an existing resource item
     *
     * @param Model $item
     */
    public function read(Model $item): void
    {

        // Show a given view and date
        $calendarDate = DateTime::createFromFormat('d-m-Y', $this->request->query->get('date'));
        if (!($calendarDate instanceof DateTime)) {
            $calendarDate = new DateTime('now');
        }
        $this->getResponse()->content->set(
            [
                'template' => '@resourceItem/read.twig',
                'data'     => [
                    'item'         => $item,
                    'currentTerm'  => $this->termMapper->findForToday(),
                    'selectedDate' => $calendarDate->format('Y-m-d'),
                    'calendarView' => $this->request->query->get('view') === 'day' ? 'timeGridDay' : '',
                    'token'        => $_SESSION['key'],
                ],
            ]
        );
    }

    /**
     * Display form for editing a resource item
     *
     * @param Model $item
     */
    public function edit(Model $item): void
    {
        // Build list of moderators and related items (for BelgianSuggest)
        $moderatorList = [];
        foreach ($item->getModeratorList() as $moderator) {
            $moderatorList[] = [
                'id'   => $moderator->getId(),
                'name' => $moderator->formatName(),
            ];
        }
        $relatedItemList = [];
        foreach ($item->getRelatedResourceList() as $relatedItem) {
            $relatedItemList[] = [
                'id'   => $relatedItem->getId(),
                'name' => $relatedItem->getName(),
            ];
        }

        $this->getResponse()->content->set(
            [
                'template' => '@resourceItem/edit.twig',
                'data'     => [
                    'item'            => $item,
                    'categoryList'    => $this->mapper->getJoinMapper('category')->findAll([
                        'order' => ['name' => 'asc',],
                    ]),
                    'moderationList'  => $this->mapper->getJoinMapper('moderation_type')->findAll(),
                    'moderatorList'   => $moderatorList,
                    'relatedItemList' => $relatedItemList,
                    'roleList'        => $this->roleMapper->findAll(),
                    'attachzone'      => $this->getAttachZoneData($item),
                ],
            ]
        );
    }

    /**
     * Backend for adding a new resource item
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function create(): void
    {
        $model = new Model();
        $this->updateItemWithFormValues(
            $model,
            $this->getRequest()->post
        );
        $this->mapper->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.read', ['id' => $model->getId(), 'format' => ''])
        );
    }

    /**
     * Backend for updating an existing resource item
     *
     * @param Model $item
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function update(Model $item): void
    {
        $this->updateItemWithFormValues(
            $item,
            $this->getRequest()->post
        );
        $this->mapper->save($item);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.read', ['id' => $item->getId(), 'format' => ''])
        );
    }

    /**
     * Deletes an item
     *
     * @param Model $item
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function delete(Model $item): void
    {
        $this->mapper->delete($item);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.browse', [])
        );
    }

    /**
     * Updates fields in the model with values from the form.
     *
     * @param Model        $model The resource to update
     * @param \ArrayObject $post  An array-like object that abstracts $_POST
     */
    protected function updateItemWithFormValues(Model $model, $post): void
    {
        $model
            ->setName($post['name'] ?? null)
            ->setRoomCode($post['room_code'] ?? null)
            ->setDescription($post['description'] ?? null)
            ->setCategory($post['resource_category_id'] ?? null)
            ->setModerationType($post['resource_moderation_type_id'] ?? null)
            ->setMinLeadTime($this->getDateIntervalFromForm($post['min_lead_time'] ?? []))
            ->setMinReservationTime($this->getDateIntervalFromForm($post['min_reservation_time'] ?? []))
            ->setMaxReservationTime($this->getDateIntervalFromForm($post['max_reservation_time'] ?? []))
            ->setCurrentTermRestriction($post['current_term_restriction'] ?? null);

        // Rectumize the file that was uploaded
        if (is_array($post['attachment'] ?? null)) {
            try {
                // Get the file out of the rectum, and put it in the resource
                $factory = new CollectorFactory();
                /** @var \Schoolbox\Resource\Storage\Model $image */
                $image   = $factory->build($post['attachment'])->getFirst();
                $model->setImage($image);
            } catch (ResourceException $e) {
                error(ErrorMapper::ERROR_USER_NOT_FOUND);
                exit;
            }
        } else {
            $model->setImage(null);
        }

        // Clear and create the moderator list
        $model->setModeratorList(new Collection());
        // because of BelgianSuggest I have to check if this is even an array!
        if (is_array($post['moderator_id'] ?? null)) {
            foreach ($post['moderator_id'] as $moderator_id) {
                $user = new User();
                $user->setId($moderator_id);
                $model->addModerator($user);
            }
        }

        // Do likewise with the related resource list
        $model->setRelatedResourceList(new Collection());
        if (is_array($post['related_resource_id'] ?? null)) {
            foreach ($post['related_resource_id'] as $related_resource_id) {
                $resource = new Model();
                $resource->setId($related_resource_id);
                $model->addRelatedResource($resource);
            }
        }

        // Do likewise with the role list
        $model->setRoleList(new Collection());
        if (is_array($post['role'] ?? null)) {
            foreach ($post['role'] as $roleId => $allowed) {
                $resourceRole = new RolePermission\Model();
                $role         = new Role();
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
    protected function getDateIntervalFromForm($formPart): \DateInterval
    {
        return new \DateInterval(
            sprintf(
                'PT%dH%dM',
                ((int)($formPart['day'] ?? 0) * 24 + (int)($formPart['hour'] ?? 0)),
                (int)($formPart['minute'] ?? 0),
            )
        );
    }

    /**
     * @param Model $model
     * @return array
     */
    protected function getAttachZoneData(Model $model): array
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload Image')
            ->setMimeFilter('image/*')
            ->setReadOnly(false)
            ->setMulti(false);

        if ($model->getImage()->getHash()) {
            $zone->attachFiles([$model->getImage()]);
        }
        return $zone->getTwigContext();
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper): self
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @param  UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper): self
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @param  TermMapper $termMapper
     *
     * @return $this
     */
    public function setTermMapper($termMapper): self
    {
        $this->termMapper = $termMapper;

        return $this;
    }

    /**
     * @param  RoleMapper $roleMapper
     * @return $this
     */
    public function setRoleMapper($roleMapper): self
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }

    /**
     * @param  IUrlPaginator $paginator
     * @return $this
     */
    public function setPaginator($paginator): self
    {
        $this->paginator = $paginator;
        return $this;
    }
}
