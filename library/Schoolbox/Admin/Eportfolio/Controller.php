<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Eportfolio;

use Alaress\Web\Controller\Base;
use Alaress\Web\Request\Values;
use Component\Eportfolio\Textbox as EportfolioTextbox;
use Component\Eportfolio\TextboxMapper as EportfolioTextboxMapper;
use Component_Config;
use Component_ConfigDefinition;
use Component_ConfigMapper;
use Component_ControllerAbstract;
use Component_Eportfolio_Factory;
use Component_Eportfolio_TextboxController as ComponentEportfolioTextboxController;
use Component_InstanceMapper;
use Eportfolio\Page as EportfolioPage;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Eportfolio\Permission as EportfolioPermission;
use Eportfolio\PermissionMapper as EportfolioPermissionMapper;
use Exception;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Admin\Eportfolio\Import\Mapper as EportfolioImportMapper;
use Schoolbox\Admin\Eportfolio\Import\Log\Mapper as EportfolioImportLogMapper;
use Schoolbox\Admin\Eportfolio\Import\Model as EportfolioImport;
use Schoolbox\Admin\Eportfolio\Import\Log\Model as EportfolioImportLog;
use SitePage;

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

    /** @var EportfolioImportMapper $eportfolioImportMapper */
    private $eportfolioImportMapper;

    /** @var EportfolioImportLogMapper $eportfolioImportLogMapper */
    private $eportfolioImportLogMapper;

    /**
     * Controller constructor.
     * @param EportfolioPageMapper $eportfolioPageMapper
     * @param RoleMapper $roleMapper
     * @param CampusMapper $campusMapper
     * @param UserMapper $userMapper
     * @param EportfolioImportMapper $eportfolioImportMapper
     * @param EportfolioImportLogMapper $eportfolioImportLogMapper
     */
    public function __construct(
        EportfolioPageMapper $eportfolioPageMapper,
        RoleMapper $roleMapper,
        CampusMapper $campusMapper,
        UserMapper $userMapper,
        EportfolioImportMapper $eportfolioImportMapper,
        EportfolioImportLogMapper $eportfolioImportLogMapper
    ) {
        $this->eportfolioPageMapper = $eportfolioPageMapper;
        $this->roleMapper = $roleMapper;
        $this->campusMapper = $campusMapper;
        $this->userMapper = $userMapper;
        $this->eportfolioImportMapper = $eportfolioImportMapper;
        $this->eportfolioImportLogMapper = $eportfolioImportLogMapper;
    }

    /**
     * Retrieve all eportfolio pages for the specified user ID
     * @param int $userId
     */
    public function getUserPages($userId)
    {
        $pages = $this->eportfolioPageMapper->findByColumn('uid', $userId, [
            'order' => ['page_title' => 'asc']
        ]);

        $options = [];

        /** @var EportfolioPage $page */
        foreach ($pages as $page) {
            $options[] = [
                'pageId' => $page->getPageId(),
                'pageTitle' => $page->getPageTitle()
            ];
        }

        $this->response = $this->responseAsJson([
            'pages' => $options
        ]);
    }

    /**
     * Display deployment screen
     */
    public function getDeploy()
    {
        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminEportfolio/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminEportfolio.getDeploy'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->routeGenerator->generate('adminEportfolio.getDeploy')),
                'roles' => $this->roleMapper->findAll(['order' => ['name' => 'asc']]),
                'campuses' => $this->campusMapper->findAll(['order' => ['name' => 'asc']])
            ],
            'Admin - Eportfolio'
        );
    }

    /**
     * Deploy eportfolio pages to destination users
     */
    public function postDeploy()
    {
        $post = $this->requestPost;

        // Do the import in a detached state so it doesn't time out

        $eportfolioImport = new EportfolioImport();
        $eportfolioImport->setOwnerId($this->getUser()->getId());
        $this->eportfolioImportMapper->save($eportfolioImport);
        $id = $eportfolioImport->getId();

        $this->postOutputCallbacks[] = function () use ($post, $id): void {
            $this->deployCallback($post, $id);
        };
        $this->response = $this->responseFromTemplate(
            '@adminEportfolio/importResults.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminEportfolio.getDeploy'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->routeGenerator->generate('adminEportfolio.getDeploy')),
                'id' => $id
            ],
        );
    }

    /**
     * Callback that does the actual copying of ePortfolio records
     *
     * @param Values $post
     * @param int $id ID of the import for logging purposes
     */
    private function deployCallback(Values $post, $id)
    {
        if ($post->getBool('dryRun', false)) {
            $this->log('Starting dry run.', $id);
        } else {
            $this->log('Starting import.', $id);
        }

        // Load source user
        try {
            $user = $this->userMapper->findById($post['sourceUserId']);
        } catch (MapperException $e) {
            $this->log('Unable to locate source user.', $id);
        }

        $nothingWrong = true;

        if (isset($user)) {
            // Load each destination user

            $destinationUsers = new Collection();

            if (!empty($post['destinationUserId'])) {
                $destinationUserArray = explode(',', $post['destinationUserId']);

                foreach ($destinationUserArray as $userId) {
                    if (!isset($destinationUsers[$userId]) && $userId != $user->getId()) {
                        try {
                            $destinationUsers = $destinationUsers->add($this->userMapper->findById($userId));
                        } catch (MapperException $e) {
                            $this->log('Unable to locate user ID ' . $userId . '.', $id);
                        }
                    }
                }
            }

            if (is_array($post->get('role'))) {
                foreach ($post->get('role') as $roleId) {
                    $destinationUsers = $destinationUsers->mergeUnique($this->userMapper->findByColumn('group_id', $roleId), 'id');
                }
            }

            // Load in users

            if (is_array($post->get('campus'))) {
                foreach ($post->get('campus') as $campusId) {
                    $campus = new Campus();
                    $campus->setId($campusId);
                    $destinationUsers = $destinationUsers->mergeUnique($this->campusMapper->findUsersInCampus($campus), 'id');
                }
            }

            // We now had a list of destination users, lets make this party happen
            /** @var SitePage $sitePage */
            $sitePage = $this->getDi()->get('sitePage');

            // Preload the component list for each page

            $factory = new Component_Eportfolio_Factory($user, $sitePage);
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

            /** @var User $destUser */
            foreach ($destinationUsers as $destUser) {
                try {
                    $this->importSingleUser($post, $id, $user, $destUser, $eportfolioList);
                } catch (Exception $e) {
                    // Something done went wrong - abort
                    $this->log('Something went wrong: ' . $e->getMessage(), $id);
                    $nothingWrong = false;
                }
            }
        }

        if ($nothingWrong) {
            $this->log('Deployment completed', $id, 'COMPLETED');
        } else {
            $this->log('Deployment completed, but one or more imports failed!', $id, 'FAILED');
        }
    }

    /**
     * Imports a single user.
     *
     * @param Values $post POST data
     * @param int $logId
     * @param User $srcUser
     * @param User $destUser
     * @param array $eportfolioList A list of pages to import.
     *                              In the format: [EportfolioPage, Component_ControllerAbstract[]]
     */
    private function importSingleUser($post, $logId, User $srcUser, User $destUser, $eportfolioList)
    {
        $dryRun = $post->getBool('dryRun', false);
        $this->log('Importing ePortfolio pages from ' . \Scrub::text($srcUser->getFullName()) . ' to ' . \Scrub::text($destUser->getFullName()), $logId);

        $destUserId = $destUser->getId();

        // Don't copy into the source user

        if ($destUserId != $srcUser->getId()) {
            $import = true;

            if (!($destUser->getEportfolioEnable())) {
                if (isset($post['enableEportfolios']) && $post['enableEportfolios'] == '1') {
                    // If this is a dry run, don't enable eportfolio either
                    if ($dryRun) {
                        $this->log('Eportfolio was disabled. Would have enabled for user.', $logId);
                    } else {
                        $destUser->setEportfolioEnable(true);
                        $this->userMapper->save($destUser);
                        $this->log('Eportfolio was disabled. Enabling for user.', $logId);
                    }
                } else {
                    $this->log('Skipping user. Eportfolio is disabled.', $logId);
                    $import = false;
                }
            }

            if ($import) {
                foreach ($eportfolioList as $eportfolio) {

                    /** @var EportfolioPage $page */
                    /** @var Component_ControllerAbstract[] $componentList */
                    [$page, $componentList] = $eportfolio;

                    // Check this user doesn't already have this page.

                    if (isset($post['skipDuplicates'])) {
                        $existingPageList = $this->eportfolioPageMapper->findByColumnList(
                            [
                                'uid' => $destUserId,
                                'page_title' => $page->getPageTitle(),
                            ]
                        );

                        if (count($existingPageList) > 0) {
                            $this->log('Skipping page "' . $page->getPageTitle() . '". Already exists on this users ePortfolio.', $logId);
                            continue;
                        }
                    }

                    // If this is a dry run, skip the import step

                    if ($dryRun) {
                        $this->log('Would have imported page "' . $page->getPageTitle() . '".', $logId);
                        continue;
                    } else {
                        $this->log('Importing page "' . $page->getPageTitle() . '".', $logId);
                    }

                    // Create a new ePortfolio page.

                    // Does this user already have a page of this name? Skip unless prompted otherwise

                    $newPage = clone $page;
                    $newPage->setOwner($this->userMapper->createModel($destUserId));
                    $this->eportfolioPageMapper->create($newPage);

                    // copy each component and its config to the new page.
                    // Ensure that they're in sequence though.

                    $i = 0;

                    foreach ($componentList as $component) {
                        $newInstance = clone $component->getInstance();
                        $newInstance
                            ->setIdentifier($newPage->getPageId())
                            ->setSequence($i++);
                        Component_InstanceMapper::getInstance()->create($newInstance);

                        // Copy each config

                        /** @var Component_ConfigDefinition $configDefinition */
                        foreach ($newInstance->getConfig() as $configDefinition) {

                            /** @var Component_Config $config */
                            $config = new Component_Config();

                            $config->setConfigDefinitionId($configDefinition->getId())
                                ->setInstanceId($newInstance->getId())
                                ->setValue($configDefinition->getValue());

                            Component_ConfigMapper::getInstance()->create($config);
                        }

                        // Clone a new textbox for any textbox components
                        if ($component instanceof ComponentEportfolioTextboxController) {
                            try {
                                /** @var EportfolioTextbox $textbox */
                                $textbox = EportfolioTextboxMapper::getInstance()->findOneByColumn(
                                    'component_instance_id',
                                    $component->getInstance()->getId()
                                );

                                $newTextbox = clone $textbox;
                                $newTextbox->setComponentInstanceId($newInstance->getId());
                                EportfolioTextboxMapper::getInstance()->create($newTextbox);
                            } catch (MapperException $e) {
                                // This component has no textbox yet, that's okay.
                            }
                        }
                    }

                    // Copy only the allow parents permission.
                    $parentPermission = 0;

                    /** @var EportfolioPermission $perm */
                    foreach (EportfolioPermissionMapper::getInstance()->findParentSetting($page->getPageId()) as $perm) {
                        $parentPermission = $perm->getValue();
                    }

                    EportfolioPermissionMapper::getInstance()->updatePermissions(
                        $newPage->getPageId(),
                        $parentPermission,
                        [],
                        []
                    );
                }
            }
        } else {
            $this->log('Skipping user. Source user is the same as destination user.', $logId);
        }
    }

    /**
     * Save a log message
     * @param string $message
     * @param int $id
     * @param string $status
     */
    private function log($message, $id, $status = null)
    {
        $log = new EportfolioImportLog();
        $log->setImportId($id);
        $log->setResult($message);
        $this->eportfolioImportLogMapper->save($log);

        if ($status != null) {
            $import = $this->eportfolioImportMapper->findById($id);
            $import->setStatus($status);
            $this->eportfolioImportMapper->save($import);
        }
    }

    /**
     * Show import progress
     * @param int $id Import ID
     * @param int $skip Number of items to skip
     */
    public function getImportProgress($id, $skip = 0)
    {
        try {
            $import = $this->eportfolioImportMapper->findById($id);
            $importLogs = $this->eportfolioImportLogMapper->findByColumn('import_id', $id)->sortBy('id', 'asc');

            $result = [];
            $result['status'] = $import->getStatus();
            $result['logs'] = [];

            $count = 0;

            /** @var EportfolioImportLog $log */
            foreach ($importLogs as $log) {
                if ($skip > 0) {
                    $render = false;
                    $count++;
                    if ($count > $skip) {
                        $render = true;
                    }
                } else {
                    $render = true;
                }

                if ($render) {
                    $result['logs'][] = [
                        'created_at' =>
                            $log->getCreatedAt()->get24Hour() . ":" .
                            ($log->getCreatedAt()->getMinute() < 10 ? '0' . $log->getCreatedAt()->getMinute() : $log->getCreatedAt()->getMinute()) . ":" .
                            ($log->getCreatedAt()->getSecond() < 10 ? '0' . $log->getCreatedAt()->getSecond() : $log->getCreatedAt()->getSecond()),
                        'result' => $log->getResult()
                    ];
                }
            }

            $this->response = $this->responseAsJson($result);
        } catch (MapperException $e) {
            // Requested import didn't exist,
            $this->response = $this->responseAsJson([]);
        }
    }
}
