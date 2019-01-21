<?php
declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Web\Controller\Base;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Profile\Button\Campus\Availability\Mapper as CampusAvailabilityMapper;
use Schoolbox\Profile\Button\Campus\Availability\Model as ProfileButtonCampus;
use Schoolbox\Profile\Button\Role\Availability\Mapper as RoleAvailabilityMapper;
use Schoolbox\Profile\Button\Role\Availability\Model as ProfileButtonRole;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Profile\Button\Model as ProfileButton;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;

/**
 * @package Schoolbox\Admin\Profile\Button
 */
class Controller extends Base
{
    private const PAGE_CURRENT = 'profile-button';

    /** @var IconMapper $iconMapper */
    private $iconMapper;

    /** @var ProfileButtonMapper $profileButtonMapper */
    private $profileButtonMapper;

    /** @var CampusAvailabilityMapper $campusAvailabilityMapper */
    private $campusAvailabilityMapper;

    /** @var RoleAvailabilityMapper $profileButtonRoleMapper */
    private $roleAvailabilityMapper;

    /** @var RoleMapper $roleMapper */
    private $roleMapper;

    /** @var CampusMapper $campusMapper */
    private $campusMapper;

    public function __construct(
      IconMapper $iconMapper,
      ProfileButtonMapper $profileButtonMapper,
      RoleMapper $roleMapper,
      CampusMapper $campusMapper,
      CampusAvailabilityMapper $campusAvailabilityMapper,
      RoleAvailabilityMapper $roleAvailabilityMapper
    )
    {
        $this->iconMapper = $iconMapper;
        $this->profileButtonMapper = $profileButtonMapper;
        $this->roleMapper = $roleMapper;
        $this->campusMapper = $campusMapper;
        $this->campusAvailabilityMapper = $campusAvailabilityMapper;
        $this->roleAvailabilityMapper = $roleAvailabilityMapper;
    }

    /**
     * Display re-order screen
     *
     * @api   GET /adminv2/profile-button
     * @level 182
     */
    public function getIndex(): void
    {
        $buttons = $this->profileButtonMapper->findAll()->sortBy('sortOrder', 'asc');

        $rolesSelected = [];
        $campusesSelected = [];
        foreach($buttons as $button) {
            $roleSelections = $this->roleAvailabilityMapper->findByColumn('profile_button_id', $button->getId());

            $rolesForButton = [];

            /** @var ProfileButtonRole $selection */
            foreach ($roleSelections as $roleSelection) {
                $rolesForButton[$roleSelection->getRoleId()] = true;
            }

            $campusSelections = $this->campusAvailabilityMapper->findByColumn('profile_button_id', $button->getId());

            $campusesForButton = [];

            /** @var ProfileButtonCampus $selection */
            foreach ($campusSelections as $campusSelection) {
                $campusesForButton[$campusSelection->getCampusId()] = true;
            }

            $rolesSelected[$button->getId()] = $rolesForButton;
            $campusesSelected[$button->getId()] = $campusesForButton;
        }

        $this->getResponse()->content->set([
            'template' => '@adminProfileButton/index.twig',
            'data'     => [
                'pageCurrent' => self::PAGE_CURRENT,
                'profile' => $this->profileButtonMapper->findAll()->sortBy('sortOrder', 'asc'),
                'roles' => $this->roleMapper->findAll()->sortBy('name', 'asc'),
                'rolesSelected' => $rolesSelected,
                'campuses' => $this->campusMapper->findAll()->sortBy('name', 'asc'),
                'campusesSelected' => $campusesSelected
            ],
            'title' => 'Admin - Profile Buttons'
        ]);
    }

    /**
     * Display modification screen
     *
     * @api   GET /adminv2/profile-button/modify/{id}
     * @level 182
     *
     * @param $id
     */
    public function getModify(int $id): void
    {
        // Get selected campuses and roles

        $roleSelections = $this->roleAvailabilityMapper->findByColumn('profile_button_id', $id);

        $rolesSelected = [];

        /** @var ProfileButtonRole $selection */
        foreach ($roleSelections as $roleSelection) {
            $rolesSelected[$roleSelection->getRoleId()] = true;
        }

        $campusSelections = $this->campusAvailabilityMapper->findByColumn('profile_button_id', $id);

        $campusesSelected = [];

        /** @var ProfileButtonCampus $selection */
        foreach ($campusSelections as $campusSelection) {
            $campusesSelected[$campusSelection->getCampusId()] = true;
        }

        $this->getResponse()->content->set([
            'template' => '@adminProfileButton/modify.twig',
            'data'     => [
                'pageCurrent' => self::PAGE_CURRENT,
                'item' => $this->profileButtonMapper->findOneByColumn($this->profileButtonMapper->getIdentifierColumn(), $id, [], ['cache'=>false]),
                'icons' => $this->iconMapper->findAll()->sortBy('title', 'asc'),
                'roles' => $this->roleMapper->findAll()->sortBy('name', 'asc'),
                'rolesSelected' => $rolesSelected,
                'campuses' => $this->campusMapper->findAll()->sortBy('name', 'asc'),
                'campusesSelected' => $campusesSelected                
            ],
            'title' => 'Admin - Modify Profile Button'
        ]);
    }

    /**
     * Display create screen
     *
     * @api   GET /adminv2/insert
     * @level 182
     *
     */
    public function getCreate(): void
    {
        $this->getResponse()->content->set([
            'template' => '@adminProfileButton/modify.twig',
            'data'     => [
                'pageCurrent' => self::PAGE_CURRENT,
                'icons' => $this->iconMapper->findAll()->sortBy('title', 'asc'),
                'roles' => $this->roleMapper->findAll()->sortBy('name', 'asc'),
                'campuses' => $this->campusMapper->findAll()->sortBy('name', 'asc')
            ],
            'title' => 'Admin - Create Profile Button'
        ]);
    }

    /**
     * Update profile button order
     *
     * @api   POST /adminv2/profile-button/order
     * @level 182
     */
    public function postOrder(): void
    {
        $post = $this->getRequest()->post;
        $orderArray = explode(",", $post['order']);

        $order = 0;
        foreach ($orderArray as $id) {
            try {
                /** @var ProfileButton $item */
                $item = $this->profileButtonMapper->findById($id);
                $item->setSortOrder($order++);
                $this->profileButtonMapper->save($item);
            } catch (MapperException $e) {
            }
        }
    }

    /**
     * Delete profile button
     *
     * @api   POST /adminv2/profile-button/delete
     * @level 182
     */
    public function postDelete(): void
    {
        $post = $this->getRequest()->post;
        if (!empty($post['id'])) {
            $this->profileButtonMapper->deleteByColumn("id", $post["id"]);
        }
    }

    /**
     * Save profile button
     *
     * @api   POST /adminv2/profile-button/modify
     * @level 182
     */
    public function postModify(): void
    {
        $post = $this->getRequest()->post;

        if (!empty($post['id'])) {
            $item = $this->profileButtonMapper->findById($post['id']);
        } else {
            $item = new ProfileButton();

            $itemOrder = $this->profileButtonMapper->findAll([
                'limit' => 1,
                'order' => [
                    'sort_order' => 'DESC'
                ]
            ]);

            if ($itemOrder->count()) {
                $item->setSortOrder($itemOrder->first()->getSortOrder() + 1);
            } else {
                $item->setSortOrder(1);
            }
        }

        $item->setTitle($post['title']);
        $item->setText($post['text']);
        $item->setIcon($post['icon']);
        $item->setUrl($post['url']);
        $item->setTarget($post['target']);
        
        $this->profileButtonMapper->save($item);

        $this->campusAvailabilityMapper->deleteByColumn('profile_button_id', $item->getId());

        if (is_array($post['campuses'])) {
            foreach ($post['campuses'] as $campusId) {
                $profileButtonCampus = new ProfileButtonCampus();
                $profileButtonCampus->setProfileButtonId($item->getId());
                $profileButtonCampus->setCampusId((int)$campusId);
                $this->campusAvailabilityMapper->save($profileButtonCampus);
            }
        }
        
        $this->roleAvailabilityMapper->deleteByColumn('profile_button_id', $item->getId());

        if (is_array($post['roles'])) {
            foreach ($post['roles'] as $roleId) {
                $profileButtonRole = new ProfileButtonRole();
                $profileButtonRole->setProfileButtonId($item->getId());
                $profileButtonRole->setRoleId((int)$roleId);
                $this->roleAvailabilityMapper->save($profileButtonRole);
            }
        }
        
        $this->getResponse()->redirect->afterPost("/adminv2/profile-button");
    }
}
