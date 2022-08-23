<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\User\BulkModify;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use HouseMapper;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\User\BulkUpdater;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\RoleMapper;
use Schoolbox\UserBulkMapper;
use Schoolbox\YearLevel;

/**
 * Controller for bulk modifying users through the admin section.
 *
 * @package Schoolbox\Admin\Users\BulkModify
 */
class Controller extends Base
{
    /** @var WritableCache */
    protected WritableCache $cache;

    /** @var UserBulkMapper */
    protected UserBulkMapper $userBulkMapper;

    /** @var CampusMapper */
    protected CampusMapper $campusMapper;

    /** @var RoleMapper */
    protected RoleMapper $roleMapper;

    /** @var HouseMapper */
    protected HouseMapper $houseMapper;

    /** @var YearLevel\Mapper */
    protected YearLevel\Mapper $yearLevelMapper;


    public function __construct(
        WritableCache $cache,
        UserBulkMapper $userBulkMapper,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseMapper $houseMapper,
        YearLevel\Mapper $yearLevelMapper
    ) {
        $this->cache           = $cache;
        $this->userBulkMapper  = $userBulkMapper;
        $this->campusMapper    = $campusMapper;
        $this->roleMapper      = $roleMapper;
        $this->houseMapper     = $houseMapper;
        $this->yearLevelMapper = $yearLevelMapper;
    }

    /**
     * Bulk user modify page
     * @return void
     * @throws RouteNotFound
     * @api POST /adminv2/user/bulk/modify/list
     */
    public function getModify(): void
    {
        $campusList = $this->campusMapper->findAll()->sortBy('sequence', 'ASC');
        $campuses   = [];
        foreach ($campusList as $campus) {
            $campuses[] = [
                'id'   => $campus->getId(),
                'name' => $campus->getName(),
            ];
        }
        $roleList = $this->roleMapper->findAll()->sortBy('sequence', 'ASC');
        $roles    = [];
        foreach ($roleList as $role) {
            $roles[] = [
                'id'   => $role->getId(),
                'name' => $role->getName(),
            ];
        }
        $yearLevelList = $this->yearLevelMapper->findAll()->sortBy('sequence', 'ASC');
        $yearLevels    = [];
        foreach ($yearLevelList as $yearLevel) {
            $yearLevels[] = [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        }
        $houseList = $this->houseMapper->findAll()->sortBy('sequence', 'ASC');
        $houses    = [];
        /** @var \House $house */
        foreach ($houseList as $house) {
            $houses[] = [
                'id'   => $house->getId(),
                'name' => $house->getHouse(),
            ];
        }

        $this->getResponse()->content->set(
            [
                'template' => '@adminUserBulkModify/bulkModify.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('adminUserBulkModify.getModify'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs(
                        $this->router->generate('adminUserBulkModify.getModify'),
                        'Bulk Modify'
                    ),
                    'campuses'       => $campuses,
                    'roles'          => $roles,
                    'yearLevels'     => $yearLevels,
                    'houses'         => $houses,
                ],
                'title'    => 'Admin - User Management',
            ]
        );
    }

    /**
     * Populate bulk modify page with users with selected conditions
     * @api POST /adminv2/user/bulk/modify/list
     */
    public function getUserList()
    {
        $selected = json_decode($this->request->content->get(), true);
        /* If there were any search results, display control options
         */
        $userList = $this->userBulkMapper->bulkModifySearch(
            $selected['campusId'] ? [(int)$selected['campusId']] : [UserBulkMapper::ANY],
            $selected['roleId'] ? [(int)$selected['roleId']] : [UserBulkMapper::ANY],
            (int)$selected['ePortfolio'],
            $selected['yearLevelId'] ? [(int)$selected['yearLevelId']] : [UserBulkMapper::ANY],
            (int)$selected['token']
        );
        $this->getResponse()->content->set($this->buildJsonOutput($userList));
    }

    /**
     * Perform bulk update
     * @api POST /adminv2/user/bulk/modifyForm
     */
    public function postModify()
    {
        $params  = $this->request->post;
        $userIds = $params->get('users');

        if (!is_array($userIds) || count($userIds) === 0) {
            error(ErrorMapper::ERROR_PROCESSING, 'No user selected.');
        }

        $this->extendExecutionTime();

        $message = "{$this->getUser()->getFullname()} ({$this->getUser()->getId()}) started a user bulk modify";
        $this->userBulkMapper->log($message);

        try {
            $modified = (new BulkUpdater(
                $this->getUser(),
                $this->userBulkMapper,
                $userIds,
                array_filter(explode(',', $params->get('campusIds'))),
                (int)$params->get('roleId'),
                (int)$params->get('yearLevelId'),
                (int)$params->get('houseId'),
                (int)$params->get('ePortfolio'),
                (int)$params->get('emailFromUserName'),
                (int)$params->get('externalMailClient'),
                (int)$params->get('webmail'),
                (int)$params->get('accountEnabled'),
                (int)$params->get('hideContacts'),
                (int)$params->get('hideTimetable'),
                $params->get('positionTitle'),
                (int)$params->get('displayGradeGraph'),
                (int)$params->get('revokeToken')
            ))->update();
        } catch (SchoolboxException $e) {
            $problem = (new ApiProblem('Campus has been deleted or does not exist'))
                ->setStatus(501)
                ->asArray();
            $this->getResponse()->status->setCode(501);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return;
        }

        // refresh subscription cache
        $this->cache->cacheUsers(INSTANCE_ID, $userIds);

        $jsonPayload = [
            \Scrub::text($this->userBulkMapper->getOutputMessage()),
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($jsonPayload));
    }

    /**
     * Delete Bulk Users
     * @api DELETE /adminv2/user/bulk/modifyForm
     */
    public function deleteUsers()
    {
        $params  = $this->request->post;
        $userIds = $params->get('users');

        if (!is_array($userIds) || count($userIds) === 0) {
            error(ErrorMapper::ERROR_PROCESSING, 'No user selected.');
        }

        $this->extendExecutionTime();
        $message = "{$this->getUser()->getFullname()} ({$this->getUser()->getId()}) started a user bulk delete";
        $this->userBulkMapper->log($message);


        $userCollection = $this->userBulkMapper->findByColumn('id', $userIds);
        $this->userBulkMapper->bulkDelete($userCollection);

        $jsonPayload = [
            \Scrub::text($this->userBulkMapper->getOutputMessage()),
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($jsonPayload));
    }
}
