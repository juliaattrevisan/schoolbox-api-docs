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

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Browses the list of items.
     */
    public function browse() {
        $list = $this->getMapper()->findAll();
        $this->getResponse()->content->set(
            array(
                'template' => __DIR__.'/templates/browse.twig',
                'data'     => array(
                    'list' => $list,
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
                'template' => __DIR__.'/templates/edit.twig',
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
                'template' => __DIR__.'/templates/read.twig',
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
                'template' => __DIR__.'/templates/edit.twig',
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
            // Let's just get started
            ->setMinLeadTime(new \DateInterval('PT1H'))
            ->setMinReservationTime(new \DateInterval('PT1H'))
            ->setMaxReservationTime(new \DateInterval('PT24H'))
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
        ;
        // Clear and create the moderator list
        $model->setModeratorList(new Collection());
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
