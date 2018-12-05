<?php
declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Web\Controller\Base;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Profile\Button\Campus\Mapper as ProfileButtonCampusMapper;
use \Schoolbox\Profile\Button\Role\Mapper as ProfileButtonRoleMapper;
use Schoolbox\Profile\Button\Campus\Model as ProfileButtonCampus;
use Schoolbox\Profile\Button\Role\Model as ProfileButtonRole;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Profile\Button\Model as ProfileButton;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;

/**
 * @package Schoolbox\Admin\Profile\Button
 */
class Controller extends Base
{
    const PAGE_CURRENT = 'profile-button';

    /** @var IconMapper $iconMapper */
    private $iconMapper;

    /** @var ProfileButtonMapper $profileButtonMapper */
    private $profileButtonMapper;

    /** @var ProfileButtonCampusMapper $profileButtonCampusMapper */
    private $profileButtonCampusMapper;

    /** @var ProfileButtonRoleMapper $profileButtonRoleMapper */
    private $profileButtonRoleMapper;

    /** @var RoleMapper $roleMapper */
    private $roleMapper;

    /** @var CampusMapper $campusMapper */
    private $campusMapper;

    public function __construct()
    {
        $this->setIconMapper(IconMapper::getInstance());
        $this->setProfileButtonMapper(ProfileButtonMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setCampusMapper(CampusMapper::getInstance());
        $this->setProfileButtonCampusMapper(ProfileButtonCampusMapper::getInstance());
        $this->setProfileButtonRoleMapper(ProfileButtonRoleMapper::getInstance());
    }

    /**
     * Display re-order screen
     *
     * @api   GET /adminv2/profile-button
     * @level 182
     */
    public function getIndex(): void
    {
        $this->getResponse()->content->set([
            'template' => '@adminProfileButton/index.twig',
            'data'     => [
                'pageCurrent' => self::PAGE_CURRENT,
                'profile' => $this->getProfileButtonMapper()->findAll()->sortBy('sortOrder', 'asc')
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

        $roleSelections = $this->getProfileButtonRoleMapper()->findByColumn('profile_button_id', $id);

        $rolesSelected = [];

        /** @var ProfileButtonRole $selection */
        foreach ($roleSelections as $roleSelection) {
            $rolesSelected[$roleSelection->getRoleId()] = true;
        }

        $campusSelections = $this->getProfileButtonCampusMapper()->findByColumn('profile_button_id', $id);

        $campusesSelected = [];

        /** @var ProfileButtonCampus $selection */
        foreach ($campusSelections as $campusSelection) {
            $campusesSelected[$campusSelection->getCampusId()] = true;
        }

        $this->getResponse()->content->set([
            'template' => '@adminProfileButton/modify.twig',
            'data'     => [
                'pageCurrent' => self::PAGE_CURRENT,
                'item' => $this->getProfileButtonMapper()->findOneByColumn($this->getProfileButtonMapper()->getIdentifierColumn(), $id, [], ['cache'=>false]),
                'icons' => $this->getIconMapper()->findAll()->sortBy('title', 'asc'),
                'roles' => $this->getRoleMapper()->findAll()->sortBy('name', 'asc'),
                'rolesSelected' => $rolesSelected,
                'campuses' => $this->getCampusMapper()->findAll()->sortBy('name', 'asc'),
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
                'icons' => $this->getIconMapper()->findAll()->sortBy('title', 'asc'),
                'roles' => $this->getRoleMapper()->findAll()->sortBy('name', 'asc'),
                'campuses' => $this->getCampusMapper()->findAll()->sortBy('name', 'asc')
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
                $item = $this->getProfileButtonMapper()->findById($id);
                $item->setSortOrder($order++);
                $this->getProfileButtonMapper()->save($item);
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
        if (!empty($post['id'])) {
            $post = $this->getRequest()->post;
            $this->getProfileButtonMapper()->deleteByColumn("id", $post["id"]);
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
            $item = $this->getProfileButtonMapper()->findById($post['id']);
        } else {
            $item = new ProfileButton();

            $itemOrder = $this->getProfileButtonMapper()->findAll([
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

        // Populate token

        if (stripos($post['url'], '/learning/classes') === 0) {
            $item->setToken('CLASSES');
        } else if (stripos($post['url'], '/learning/due') === 0) {
            $item->setToken('SUBMISSION');
        } else if (stripos($post['url'], '/timetable') === 0) {
            $item->setToken('TIMETABLE');
        } else if (stripos($post['url'], '/calendar/week') === 0) {
            $item->setToken('CALENDAR');
        } else if (stripos($post['url'], '/news') === 0) {
            $item->setToken('NEWS');
        } else if (stripos($post['url'], '/learning/grades') === 0) {
            $item->setToken('GRADE');
        } else if (stripos($post['url'], '/course/list') === 0) {
            $item->setToken('COURSE');
        } else if (stripos($post['url'], '/resources') === 0) {
            $item->setToken('RESOURCES');
        } else if (stripos($post['url'], '/eportfolio') === 0) {
            $item->setToken('EPORTFOLIO');
        } else if (stripos($post['url'], '/my-files') === 0) {
            $item->setToken('MYFILES');
        } else if (stripos($post['url'], '/drives') === 0) {
            $item->setToken('NETWORK');
        } else {
            $item->setToken('');
        }
        
        $this->getProfileButtonMapper()->save($item);

        $this->getProfileButtonCampusMapper()->deleteByColumn('profile_button_id', $item->getId());

        if (is_array($post['campuses'])) {
            foreach ($post['campuses'] as $campusId) {
                $profileButtonCampus = new ProfileButtonCampus();
                $profileButtonCampus->setProfileButtonId($item->getId());
                $profileButtonCampus->setCampusId((int)$campusId);
                $this->getProfileButtonCampusMapper()->save($profileButtonCampus);
            }
        }
        
        $this->getProfileButtonRoleMapper()->deleteByColumn('profile_button_id', $item->getId());

        if (is_array($post['roles'])) {
            foreach ($post['roles'] as $roleId) {
                $profileButtonRole = new ProfileButtonRole();
                $profileButtonRole->setProfileButtonId($item->getId());
                $profileButtonRole->setRoleId((int)$roleId);
                $this->getProfileButtonRoleMapper()->save($profileButtonRole);
            }
        }
        
        $this->getResponse()->redirect->afterPost("/adminv2/profile-button");
    }

    /** Here be mappers and stuff */

    /**
     * @return IconMapper
     */
    public function getIconMapper(): IconMapper
    {
        return $this->iconMapper;
    }

    /**
     * @param IconMapper $mapper
     * @return $this
     */
    public function setIconMapper(IconMapper $mapper): self
    {
        $this->iconMapper = $mapper;
        return $this;
    }

    // Button profile mapper

    /**
     * @return ProfileButtonMapper
     */
    public function getProfileButtonMapper(): ProfileButtonMapper
    {
        return $this->profileButtonMapper;
    }

    /**
     * @param ProfileButtonMapper $mapper
     * @return $this
     */
    public function setProfileButtonMapper(ProfileButtonMapper $mapper): self
    {
        $this->profileButtonMapper = $mapper;
        return $this;
    }

    /**
     * @return ProfileButtonCampusMapper
     */
    public function getProfileButtonCampusMapper(): ProfileButtonCampusMapper
    {
        return $this->profileButtonCampusMapper;
    }

    /**
     * @param ProfileButtonCampusMapper $mapper
     * @return $this
     */
    public function setProfileButtonCampusMapper(ProfileButtonCampusMapper $mapper): self
    {
        $this->profileButtonCampusMapper = $mapper;
        return $this;
    }

    /**
     * @return ProfileButtonRoleMapper
     */
    public function getProfileButtonRoleMapper(): ProfileButtonRoleMapper
    {
        return $this->profileButtonRoleMapper;
    }

    /**
     * @param ProfileButtonRoleMapper $mapper
     * @return $this
     */
    public function setProfileButtonRoleMapper(ProfileButtonRoleMapper $mapper): self
    {
        $this->profileButtonRoleMapper = $mapper;
        return $this;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper(): RoleMapper
    {
        return $this->roleMapper;
    }

    /**
     * @param RoleMapper $mapper
     * @return $this
     */
    public function setRoleMapper(RoleMapper $mapper): self
    {
        $this->roleMapper = $mapper;
        return $this;
    }

    /**
     * @return CampusMapper
     */
    public function getCampusMapper(): CampusMapper
    {
        return $this->campusMapper;
    }

    /**
     * @param CampusMapper $mapper
     * @return $this
     */
    public function setCampusMapper(CampusMapper $mapper): self
    {
        $this->campusMapper = $mapper;
        return $this;
    }
}
