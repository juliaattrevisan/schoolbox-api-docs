<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Eportfolio;

use Alaress\Web\Controller\Base;
use Component\Eportfolio\TextboxMapper as EportfolioTextboxMapper;
use Component_Eportfolio_TextboxController as ComponentEportfolioTextboxController;
use Eportfolio\Page as EportfolioPage;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Eportfolio\PermissionMapper as EportfolioPermissionMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Admin\Eportfolio\Import\Mapper as EportfolioImportMapper;
use Schoolbox\Admin\Eportfolio\Import\Log\Mapper as EportfolioImportLogMapper;
use Schoolbox\Admin\Eportfolio\Import\Model as EportfolioImport;
use Schoolbox\Admin\Eportfolio\Import\Log\Model as EportfolioImportLog;

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

    /**
     * Display deployment screen
     */
    public function getDeploy()
    {
        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminEportfolio/index.twig',
            'data' => [
                'pageCurrent' => 'eportfolio/deploy',
                'roles' => $this->roleMapper->findAll(['order' => ['name' => 'asc']]),
                'campuses' => $this->campusMapper->findAll(['order' => ['name' => 'asc']])
            ],
            'title' => 'Admin - Eportfolio'
        ]);
    }

    /**
     * Deploy eportfolio pages to destination users
     */
    public function postDeploy()
    {
        $post = $this->getRequest()->post;

        // Do the import in a detached state so it doesn't time out
        $eportfolioImport = new EportfolioImport();
        $eportfolioImport->setOwnerId($this->getUser()->getId());
        $eportfolioImport->setCreatedAt(\Alaress_Date_Format::now());
        $eportfolioImport->setUpdatedAt(\Alaress_Date_Format::now());
        $this->eportfolioImportMapper->save($eportfolioImport);
        $id = $eportfolioImport->getId();

        $this->getResponse()->content->set([
            'template' => '@adminEportfolio/importResults.twig',
            'data'     => array(
                'pageCurrent' => 'eportfolio',
                'id' => $id
            ),
            'postOutput' => [
                function() use ($post, $id) {
                    $this->deployCallback($post, $id);
                }
            ],
        ]);
    }

    /**
     * Callback that does the actual copying of ePortfolio records
     * @param \Aura\Web\Request\Values $post
     * @param int $id ID of the import for logging purposes
     */
    private function deployCallback($post, $id) {

        if ($post['dryRun'] == '1') {
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

        if (isset($user)) {
            $destinationUsers = new Collection();

            // Load each destination user

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

            if (is_array($post['role'])) {
                foreach ($post['role'] as $roleId) {
                    $destinationUsers = $destinationUsers->mergeUnique($this->userMapper->findByColumn('group_id', $roleId));
                }
            }

            // Load in users

            if (is_array($post['campus'])) {
                foreach ($post['campus'] as $campusId) {
                    $campus = new Campus();
                    $campus->setId($campusId);
                    $destinationUsers = $destinationUsers->mergeUnique($this->campusMapper->findUsersInCampus($campus));
                }
            }

            // We now had a list of destination users, lets make this party happen

            $page = new \SitePage(false);
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

            /** @var \Schoolbox\User $destUser */
            foreach ($destinationUsers as $destUser) {

                $this->log('Importing ePortfolio pages from ' . $user->getFullName() . ' to ' . $destUser->getFullName(), $id);

                $destUserId = $destUser->getId();

                // Don't copy into the source user

                if ($destUserId != $user->getId()) {

                    $import = true;

                    if (!($destUser->getEportfolioEnable())) {
                        if ($post['enableEportfolios'] == '1') {
                            $destUser->setEportfolioEnable(true);
                            $this->userMapper->save($destUser);
                            $this->log('Eportfolio was disabled. Enabling for user.', $id);
                        } else {
                            $this->log('Skipping user. Eportfolio is disabled.', $id);
                            $import = false;
                        }
                    }

                    if ($import) {
                        foreach ($eportfolioList as $eportfolio) {

                            /** @var EportfolioPage $page */
                            /** @var \Component_ControllerAbstract[] $componentList */
                            list($page, $componentList) = $eportfolio;

                            // Check this user doesn't already have this page.

                            if ($post['skipDuplicates']) {

                                $existingPageList = $this->eportfolioPageMapper->findByColumnList(
                                    array(
                                        'uid' => $destUserId,
                                        'page_title' => $page->getPageTitle(),
                                    )
                                );

                                if (count($existingPageList) > 0) {
                                    $this->log('Skipping page "' . $page->getPageTitle() . '". Already exists on this users ePortfolio.', $id);
                                    continue;
                                }
                            }

                            // If this is a dry run, skip the import step

                            if ($post['dryRun'] == '1') {
                                $this->log('Would have imported page "' . $page->getPageTitle() . '".', $id);
                                continue;
                            } else {
                                $this->log('Importing page "' . $page->getPageTitle() . '".', $id);
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
                                if ($component instanceof ComponentEportfolioTextboxController) {
                                    try {
                                        /** @var \Component\Eportfolio\Textbox $textbox */
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

                            /** @var \Eportfolio\Permission $perm */
                            foreach (EportfolioPermissionMapper::getInstance()->findParentSetting($page->getPageId()) as $perm) {
                                $parentPermission = $perm->getValue();
                            }

                            EportfolioPermissionMapper::getInstance()->updatePermissions(
                                $newPage->getPageId(),
                                $parentPermission,
                                array(),
                                array()
                            );
                        }
                    }
                } else {
                    $this->log('Skipping user. Source user is the same as destination user.', $id);
                }
            }
        }

        $this->log('Deployment completed', $id, 'COMPLETED');
    }

    /**
     * Save a log message
     * @param $message
     * @param $id
     * @param $status
     */
    private function log($message, $id, $status=null) {
        $log = new EportfolioImportLog();
        $log->setImportId($id);
        $log->setResult($message);
        $log->setUpdatedAt(\Alaress_Date_Format::now());
        $log->setCreatedAt(\Alaress_Date_Format::now());
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

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => $this->buildJsonOutput($result)
            ]);

        } catch (MapperException $e) {

            // Requested import didn't exist,

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => []
            ]);
        }
    }
}
