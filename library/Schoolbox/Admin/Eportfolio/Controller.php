<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Eportfolio;

use Alaress\Web\Controller\Base;
use Eportfolio\Page as EportfolioPage;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terms
 */
class Controller extends Base
{
    /** @var EportfolioPageMapper $eportfolioPageMapper */
    private $eportfolioPageMapper;

    /** @var RoleMapper $roleMapper */
    private $roleMapper;

    /** @var CampusMapper $campusMapper */
    private $campusMapper;

    /** @var UserMapper $userMapper */
    private $userMapper;

    /**
     * Controller constructor.
     */
    public function __construct(
        EportfolioPageMapper $eportfolioPageMapper,
        RoleMapper $roleMapper,
        CampusMapper $campusMapper,
        UserMapper $userMapper
    ) {
        $this->eportfolioPageMapper = $eportfolioPageMapper;
        $this->roleMapper = $roleMapper;
        $this->campusMapper = $campusMapper;
        $this->userMapper = $userMapper;
    }

    /**
     * Retrieve all eportfolio pages for the specified user ID
     * @param $userId
     */
    public function getUserPages($userId)
    {
        $pages = $this->eportfolioPageMapper->findByColumn('uid', $userId, [
            'order' => ['page_title' => 'asc']
        ]);

        $options = [];

        /** @var \Eportfolio\Page $page */
        foreach ($pages as $page) {
            $options[] = [
                'pageId' => $page->getPageId(),
                'pageTitle' => $page->getPageTitle()
            ];
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => [
                'pages' => $options
            ]
        ]);
    }

    public function getDeploy()
    {
        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminEportfolio/index.twig',
            'data' => [
                'pageCurrent' => 'eportfolio',
                'roles' => $this->roleMapper->findAll(['order' => ['name' => 'asc']]),
                'campuses' => $this->campusMapper->findAll(['order' => ['name' => 'asc']])
            ],
            'title' => 'Admin - Eportfolio'
        ]);
    }

    public function postDeploy()
    {
        $post = $this->getRequest()->post;

        // Load source user

        try {
            $user = $this->userMapper->findById($post['sourceUserId']);
        } catch (MapperException $e) {
            throw $e;
        }

        $destinationUsers = new Collection();

        // Load each destination user

        if (!empty($post['destinationUserId'])) {
            $destinationUserArray = explode(',', $post['destinationUserId']);

            foreach ($destinationUserArray as $userId) {
                if (!isset($destinationUsers[$userId]) && $userId != $user->getId()) {
                    try {
                        $destinationUsers->add($this->userMapper->findById($userId));
                    } catch (MapperException $e) {
                        throw $e;
                    }
                }
            }
        }

        if (is_array($post['role'])) {
            foreach ($post['role'] as $roleId) {
                $destinationUsers->mergeUnique($this->userMapper->findByColumn('group_id', $roleId));
            }
        }

        // Load in users

        if (is_array($post['campus'])) {
            foreach ($post['campus'] as $campusId) {
                $campus = new Campus();
                $campus->setId($campusId);
                $destinationUsers->mergeUnique($this->campusMapper->findUsersInCampus($campus));
            }
        }

        // We now had a list of destination users, lets make this party happen

        $page = new \SitePage();
        $page->setDi($this->getDi());

        // Preload the component list for each page

        $factory = new \Component_Eportfolio_Factory($user, $page);
        $eportfolioList = array();

        /** @var EportfolioPage $page */
        foreach (EportfolioPageMapper::getInstance()->findPagesWithPermissions($user->getId()) as $page) {

            // Don't care about deleted pages

            if ($page->isDeleted()) {
                continue;
            }

            // Only interested in the selected pages

            if (!in_array($page->getPageId(), $post['page'])) {
                continue;
            }

            $eportfolioList[] = array($page, $factory->buildComponentList($page->getPageId()));
        }

        foreach ($destinationUsers as $destUser) {

            $destUserId = $destUser->getId();

            // Don't copy into the source user

            if ($destUserId != $user->getId()) {

                if (!($destUser->getEportfolioEnable())) {
                    if ($post['enableEportfolios'] == '1') {
                        $destUser->setEportfolioEnable(true);
                        $this->userMapper->save($destUser);
                    }
                }

                foreach ($eportfolioList as $eportfolio) {

                    /** @var EportfolioPage $page */
                    /** @var \Component_ControllerAbstract[] $componentList */
                    list($page, $componentList) = $eportfolio;

                    if ($post['skipDuplicates']) {

                        // Check this user doesn't already have this page.

                        $existingPageList = $this->eportfolioPageMapper->findByColumnList(
                            array(
                                'uid' => $destUserId,
                                'page_title' => $page->getPageTitle(),
                            )
                        );

                        if (count($existingPageList) > 0) {
                            continue;
                        }
                    }

                    // If this is a dry run, skip the import step

                    if (false) {
                        continue;
                    }

                    // Create a new ePortfolio page.

                    // Does this user already have a page of this name? Skip unless prompted otherwise

                    $newPage = clone $page;
                    $newPage->setUid($destUserId);
                    $this->eportfolioPageMapper->create($newPage);

                    // copy each component and its config to the new page.
                    // Ensure that they're in sequence though.

                    $i = 0;

                    foreach ($componentList as $component) {
                        $newInstance = clone $component->getInstance();
                        $newInstance
                            ->setIdentifier($newPage->getPageId())
                            ->setSequence($i++);
                        \Component_InstanceMapper::getInstance()->create($newInstance);

                        // Copy each config
                        foreach ($newInstance->getConfig() as $configDefinition) {
                            /** @var \Component_Config $config */
                            /** @var \Component_ConfigDefinition $configDefinition */
                            $config = new \Component_Config();
                            $config
                                ->setConfigDefinitionId($configDefinition->getId())
                                ->setInstanceId($newInstance->getId())
                                ->setValue($configDefinition->getValue());
                            \Component_ConfigMapper::getInstance()->create($config);
                        }

                        // Clone a new textbox for any textbox components
                        if ($component instanceof \Component_Eportfolio_TextboxController) {
                            try {
                                /** @var \Component\Eportfolio\Textbox $textbox */
                                $textbox = \Component\Eportfolio\TextboxMapper::getInstance()->findOneByColumn(
                                    'component_instance_id',
                                    $component->getInstance()->getId()
                                );

                                $newTextbox = clone $textbox;
                                $newTextbox->setComponentInstanceId($newInstance->getId());
                                \Component\Eportfolio\TextboxMapper::getInstance()->create($newTextbox);
                            } catch (MapperException $e) {
                                // This component has no textbox yet, that's okay.
                            }
                        }
                    }

                    // Copy only the allow parents permission.
                    $parentPermission = 0;

                    /** @var \Eportfolio\Permission $perm */
                    foreach (\Eportfolio\PermissionMapper::getInstance()->findParentSetting($page->getPageId()) as $perm) {
                        $parentPermission = $perm->getValue();
                    }

                    \Eportfolio\PermissionMapper::getInstance()->updatePermissions(
                        $newPage->getPageId(),
                        $parentPermission,
                        array(),
                        array()
                    );
                }
            }
        }

        // All done

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminEportfolio.getDeploy', []));
    }
}
