<?php
/**
 * Controller for resource items.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;
use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\StorageMapper;

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Browses the list of items.
     */
    public function browse() {

        // Get the list of categories
        $query  = array();
        $catId  = $this->getRequest()->query['category'];
        $search = trim($this->getRequest()->query['search']);
        if (strlen($search) > 0) {
            $list = $this->getMapper()->search($search);
        }
        else {
            if ($catId) {
                $query['resource_category_id'] = $catId;
            }
            $list = $this->getMapper()->findByColumnList($query);
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/browse.twig',
                'data'     => array(
                    'list'         => $list,
                    'categoryList' => $this->getMapper()->getJoinMapper('category')->findAll(),
                    'search'       => $search,
                ),
            )
        );
    }

    /**
     * Display form for adding another category
     */
    public function add() {
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/edit.twig',
                'data'     => array(
                    'item'           => new Model(),
                    'categoryList'   => $this->getMapper()->getJoinMapper('category')->findAll(),
                    'moderationList' => $this->getMapper()->getJoinMapper('moderation_type')->findAll(),
                    'moderatorList'  => array(),
                ),
            )
        );
    }

    /**
     * Views an existing category
     *
     * @param int $id
     */
    public function read($id) {
        $item = $this->getMapper()->findById($id);
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/read.twig',
                'data'     => array(
                    'item'          => $item,
                    'moderatorList' => array(),
                ),
            )
        );
    }

    /**
     * Display form for editing a category
     *
     * @param int $id
     */
    public function edit($id) {
        // findById() ought to hydrate users as well.
        /** @var Model $item */
        $item = $this->getMapper()->findById($id);
        $moderatorList = array();
        foreach ($item->getModeratorList() as $moderator) {
            $moderatorList[] = array(
                'id'   => $moderator->getId(),
                'name' => $moderator->formatName(),
            );
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@resourceItem/edit.twig',
                'data'     => array(
                    'item'           => $item,
                    'categoryList'   => $this->getMapper()->getJoinMapper('category')->findAll(),
                    'moderationList' => $this->getMapper()->getJoinMapper('moderation_type')->findAll(),
                    'moderatorList'  => $moderatorList,
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
        $model
            ->setName($post['name'])
            ->setDescription($post['description'])
            ->setCategory($post['resource_category_id'])
            ->setModerationType($post['resource_moderation_type_id'])
            ->setMinLeadTime($this->getDateIntervalFromForm($post['min_lead_time']))
            ->setMinReservationTime($this->getDateIntervalFromForm($post['min_reservation_time']))
            ->setMaxReservationTime($this->getDateIntervalFromForm($post['max_reservation_time']))
        ;
        if (is_array($post['moderator_id'])) {
            foreach ($post['moderator_id'] as $moderator_id) {
                $user = new User();
                $user->setId($moderator_id);
                $model->addModerator($user);
            }
        }
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('resourceItem.browse', array())
        );
    }

    /**
     * Backend for updating an existing category
     *
     * @param int $id
     */
    public function update($id) {
        $post  = $this->getRequest()->post;
        $model = $this->getMapper()->findById($id);
        $model
            ->setName($post['name'])
            ->setDescription($post['description'])
            ->setCategory($post['resource_category_id'])
            ->setModerationType($post['resource_moderation_type_id'])
            ->setMinLeadTime($this->getDateIntervalFromForm($post['min_lead_time']))
            ->setMinReservationTime($this->getDateIntervalFromForm($post['min_reservation_time']))
            ->setMaxReservationTime($this->getDateIntervalFromForm($post['max_reservation_time']))
        ;
        if (($upload = $this->getRequest()->files['image'])) {
            if (empty($upload['error'])) {
                // upload the file and Rectify it
                $image = StorageMapper::getInstance()->upload(
                    $upload['name'], $upload['tmp_name'], $this->getUser()->getId()
                );
                $model->setImage($image);
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
        $this->getMapper()->save($model);
        // Return something.
        // If this is a JSON request, output a result - maybe if this is a
        // HTML request, redirect somewhere.
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('resourceItem.browse', array())
        );
    }

    /**
     * Deletes an item
     *
     * @param int $id
     */
    public function delete($id) {
        $item = $this->getMapper()->findById($id);
        $this->getMapper()->delete($item);
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate('resourceItem.browse', array())
        );
    }

    /**
     * Retrieves a date interval from form data
     *
     * @param  array $formPart An array containing "day", "hour" and "minute"
     *                         keys.
     * @return \DateInterval
     * @throws \Exception if the date interval object can't be created.
     */
    protected function getDateIntervalFromForm($formPart) {
        return new \DateInterval(
            sprintf('P%dDT%dH%dM', $formPart['day'], $formPart['hour'], $formPart['minute'])
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

}
