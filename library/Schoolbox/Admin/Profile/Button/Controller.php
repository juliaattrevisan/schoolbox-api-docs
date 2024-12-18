<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Profile\Button\Campus;
use Schoolbox\Profile\Button\Role;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Profile\Button\Model as ProfileButton;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * @package Schoolbox\Admin\Profile\Button
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     * @param ErrorMapper                $errorMapper
     * @param IconMapper                 $iconMapper
     * @param ProfileButtonMapper        $profileButtonMapper
     * @param RoleMapper                 $roleMapper
     * @param CampusMapper               $campusMapper
     * @param Campus\Availability\Mapper $campusAvailabilityMapper
     * @param Role\Availability\Mapper   $roleAvailabilityMapper
     * @param Campus\Visibility\Mapper   $campusVisibilityMapper
     * @param Role\Visibility\Mapper     $roleVisibilityMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private IconMapper $iconMapper,
        private ProfileButtonMapper $profileButtonMapper,
        private RoleMapper $roleMapper,
        private CampusMapper $campusMapper,
        private Campus\Availability\Mapper $campusAvailabilityMapper,
        private Role\Availability\Mapper $roleAvailabilityMapper,
        private Campus\Visibility\Mapper $campusVisibilityMapper,
        private Role\Visibility\Mapper $roleVisibilityMapper
    ) {
    }

    /**
     * Display re-order screen
     *
     * @api   GET /adminv2/profile-button
     * @since 182
     */
    public function getIndex(string $roleType): void
    {
        $buttons = $this->profileButtonMapper
            ->findByColumn('roletype', $roleType)
            ->sortBy('sortOrder', 'asc');

        $roleAvailabilitySelected = [];
        $campusAvailabilitySelected = [];

        $roleVisibilitySelected = [];
        $campusVisibilitySelected = [];

        foreach ($buttons as $button) {
            $roleAvailabilitySelections = $this->roleAvailabilityMapper->findByColumn('profile_button_id', $button->getId());
            $roleAvailabilitySelected[$button->getId()] = array_fill_keys($roleAvailabilitySelections->indexBy('roleId')->keys(), true);

            $campusAvailabilitySelections = $this->campusAvailabilityMapper->findByColumn('profile_button_id', $button->getId());
            $campusAvailabilitySelected[$button->getId()] = array_fill_keys($campusAvailabilitySelections->indexBy('campusId')->keys(), true);

            $roleVisibilitySelections = $this->roleVisibilityMapper->findByColumn('profile_button_id', $button->getId());
            $roleVisibilitySelected[$button->getId()] = array_fill_keys($roleVisibilitySelections->indexBy('roleId')->keys(), true);

            $campusVisibilitySelections = $this->campusVisibilityMapper->findByColumn('profile_button_id', $button->getId());
            $campusVisibilitySelected[$button->getId()] = array_fill_keys($campusVisibilitySelections->indexBy('campusId')->keys(), true);
        }

        $this->response = $this->responseFromTemplate(
            '@adminProfileButton/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminProfileButton.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminProfileButton.getIndex')),
                'profile' => $buttons,
                'roles' => $this->roleMapper->findAll()->sortBy('name', 'asc'),
                'campuses' => $this->campusMapper->findAll()->sortBy('name', 'asc'),
                'roleType' => $roleType,
                'roleAvailabilitySelected' => $roleAvailabilitySelected,
                'campusAvailabilitySelected' => $campusAvailabilitySelected,
                'roleVisibilitySelected' => $roleVisibilitySelected,
                'campusVisibilitySelected' => $campusVisibilitySelected
            ],
            'Admin - Profile Buttons'
        );
    }


    /**
     * Display modification screen
     *
     * @api   GET /adminv2/profile-button/modify/{id}
     * @since 182
     *
     * @param int $id
     * @throws \InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function getModify(int $id): void
    {
        // Get selected campuses and roles

        $roleAvailabilitySelections = $this->roleAvailabilityMapper->findByColumn('profile_button_id', $id);
        $roleAvailabilitySelected = array_fill_keys($roleAvailabilitySelections->indexBy('roleId')->keys(), true);

        $campusAvailabilitySelections = $this->campusAvailabilityMapper->findByColumn('profile_button_id', $id);
        $campusAvailabilitySelected = array_fill_keys($campusAvailabilitySelections->indexBy('campusId')->keys(), true);

        $roleVisibilitySelections = $this->roleVisibilityMapper->findByColumn('profile_button_id', $id);
        $roleVisibilitySelected = array_fill_keys($roleVisibilitySelections->indexBy('roleId')->keys(), true);

        $campusVisibilitySelections = $this->campusVisibilityMapper->findByColumn('profile_button_id', $id);
        $campusVisibilitySelected = array_fill_keys($campusVisibilitySelections->indexBy('campusId')->keys(), true);

        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL) ?
            Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL))
            : null;

        /* The profile button we're editing
         */
        $button = $this->profileButtonMapper->findOneByColumn($this->profileButtonMapper->getIdentifierColumn(), $id, [ 'cache' => false ]);

        /* Roles that are relevant to the roletype for the $button that we're editing
         * sorted alphabetically for niceness
         */
        $rolesAvailability = $this->roleMapper->findByColumn('group_type', $button->getRoleType(), [
            'order' => [
                'name' => 'asc'
            ]
        ]);

        /* People outside of this button's roletype could still see it
         */
        $rolesVisibility = $this->roleMapper->findAll()->sortBy('name', 'asc');

        $this->response = $this->responseFromTemplate(
            '@adminProfileButton/modify.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminProfileButton.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminProfileButton.getIndex')),
                    [['Modify', null]]
                ),
                'item' => $button,
                'icons' => $this->iconMapper->findAll()->sortBy('title', 'asc'),
                'rolesAvailability' => $rolesAvailability,
                'rolesVisibility' => $rolesVisibility,
                'campuses' => $this->campusMapper->findAll()->sortBy('name', 'asc'),
                'roleAvailabilitySelected' => $roleAvailabilitySelected,
                'campusAvailabilitySelected' => $campusAvailabilitySelected,
                'roleVisibilitySelected' => $roleVisibilitySelected,
                'campusVisibilitySelected' => $campusVisibilitySelected,
                'returnUrl' => $returnUrl
            ],
            'Admin - Modify Profile Button'
        );
    }


    /**
     * Display create screen
     *
     * @api   GET /adminv2/profile-button/create/{roleType}
     */
    public function getCreate(string $roleType): void
    {
        /* Roles that are relevant to the roletype for the $button that we're editing
         * sorted alphabetically for niceness
         */
        $rolesAvailability = $this->roleMapper->findByColumn('group_type', $roleType, [
            'order' => [
                'name' => 'asc'
            ]
        ]);

        /* People outside of this button's roletype could still see it
         */
        $rolesVisibility = $this->roleMapper->findAll()->sortBy('name', 'asc');

        $this->response = $this->responseFromTemplate(
            '@adminProfileButton/modify.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminProfileButton.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminProfileButton.getIndex')),
                    [['Create', null]]
                ),
                'icons' => $this->iconMapper->findAll()->sortBy('title', 'asc'),
                'rolesAvailability' => $rolesAvailability,
                'rolesVisibility' => $rolesVisibility,
                'campuses' => $this->campusMapper->findAll()->sortBy('name', 'asc'),
                'roleType' => $roleType,
                'returnUrl' => null
            ],
            'Admin - Create Profile Button'
        );
    }

    /**
     * Update profile button order
     *
     * @api   POST /adminv2/profile-button/order
     * @since 182
     */
    public function postOrder(): void
    {
        $post = $this->requestPost;
        $orderArray = explode(',', $post['order']);

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
     * @since 182
     */
    public function postDelete(): void
    {
        $post = $this->requestPost;

        if (!empty($post['id'])) {
            $button = $this->profileButtonMapper->findById($post['id']);

            /* System buttons cannot be deleted
             */
            if ($button->isSystem()) {
                throw new \DomainException('System profile buttons cannot be deleted.');
            }

            $this->profileButtonMapper->delete($button);
        }
    }

    /**
     * Save profile button
     *
     * @api   POST /adminv2/profile-button/modify
     * @since 182
     */
    public function postModify(): void
    {
        $post = $this->requestPost;

        if (!empty($post['id'])) {
            $item = $this->profileButtonMapper->findById($post['id']);
        } else {
            /* You can only create Custom Buttons, not System Buttons.
             */
            $item = (new ProfileButton())
                ->setType(ProfileButton::BUTTON_CUSTOM)
                ->setRoleType($post['roleType']);

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

        /* Check whether you have all the pieces
         *   - Must have a button text
         *   - Must have a url
         *   - Must have chosen a campus and role for Availability and also for Visibility
         */
        if (!(new ValidButton())->isSatisfiedBy($item->getType(), $post->getArrayCopy())) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        $item->setSelfEnabled((int)$post['selfEnabled']);
        $item->setText(trim((string)$post['text']));
        $item->setTitle(trim((string)$post['title']));
        $item->setIcon(trim((string)$post['icon']));

        /* Only set urls for Custom Buttons
         */
        if ($item->isCustom()) {
            $item->setUrl($post['url'] ?? '');
        }

        $item->setCss(trim((string)$post['css']));
        $item->setTarget(trim((string)$post['target']));

        $this->profileButtonMapper->save($item);

        $this->campusAvailabilityMapper->deleteByColumn('profile_button_id', $item->getId());

        if (\is_array($post['campusAvailability'] ?? null)) {
            foreach ($post['campusAvailability'] as $campusId) {
                $profileButtonCampus = new Campus\Availability\Model();
                $profileButtonCampus->setProfileButtonId($item->getId());
                $profileButtonCampus->setCampusId((int)$campusId);
                $this->campusAvailabilityMapper->save($profileButtonCampus);
            }
        }

        $this->roleAvailabilityMapper->deleteByColumn('profile_button_id', $item->getId());

        if (\is_array($post['roleAvailability'] ?? null)) {
            foreach ($post['roleAvailability'] as $roleId) {
                $profileButtonRole = new Role\Availability\Model();
                $profileButtonRole->setProfileButtonId($item->getId());
                $profileButtonRole->setRoleId((int)$roleId);
                $this->roleAvailabilityMapper->save($profileButtonRole);
            }
        }

        $this->campusVisibilityMapper->deleteByColumn('profile_button_id', $item->getId());

        if (\is_array($post['campusVisibility'] ?? null)) {
            foreach ($post['campusVisibility'] as $campusId) {
                $profileButtonCampus = new Campus\Visibility\Model();
                $profileButtonCampus->setProfileButtonId($item->getId());
                $profileButtonCampus->setCampusId((int)$campusId);
                $this->campusVisibilityMapper->save($profileButtonCampus);
            }
        }

        $this->roleVisibilityMapper->deleteByColumn('profile_button_id', $item->getId());

        if (\is_array($post['roleVisibility'] ?? null)) {
            foreach ($post['roleVisibility'] as $roleId) {
                $profileButtonRole = new Role\Visibility\Model();
                $profileButtonRole->setProfileButtonId($item->getId());
                $profileButtonRole->setRoleId((int)$roleId);
                $this->roleVisibilityMapper->save($profileButtonRole);
            }
        }

        $route = $this->requestPost->get(Redirect::RETURN_URL)
            ?: $this->routeGenerator->generate('adminProfileButton.getIndex', ['roleType' => $item->getRoleType()]);

        $this->response = $this->redirectAfterPost($route);
    }
}
