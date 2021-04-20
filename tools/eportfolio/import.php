#!/usr/bin/php
<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Imports eportfolio page layouts from a user to all users within a group.
 */
$app = new class extends CliTool\App
{

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['u:', 'U:', 'r:'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['page:', 'skip-duplicates', 'enable-eportfolio', 'dry-run'];
    }


    /** @inheritDoc */
    public function run(): void
    {
        $userId           = $this->getOptAsInt('u:');
        $toUserId         = $this->getOptAsInt('U:');
        $roleId           = $this->getOptAsInt('r:');
        $importPageId     = $this->getOptAsInt('page::');
        $skipDuplicates   = $this->getOpt('skip-duplicates');
        $enableEportfolio = $this->getOpt('enable-eportfolio');
        $dryRun           = $this->getOpt('dry-run');

        // Check we got required options
        $errors = false;
        if ($userId === null) {
            $this->getLogger()->error("You must specify a user to copy from using -u");
            $errors = true;
        }
        if ($roleId === null && $toUserId === null) {
            $this->getLogger()->error("You must specify either a role or user to copy to using -r or -U");
            $errors = true;
        }
        if ($errors) {
            $this->renderHelpMessage();
            exit(1);
        }

        $this->getLogger()->info('ePortfolio Import Script' . ($dryRun ? ' - Dry Run (no updates made)' : '') . ':');

        // Load source user
        try {
            $user = UserMapper::getInstance()->findById($userId);
        } catch (MapperException $e) {
            $this->getLogger()->error(sprintf('Error: user #%s not found', $userId));
            exit(1);
        }

        // Load user list, either from toUserId or from roleId
        $role = null;

        if (isset($toUserId)) {

            /** @var User[] $destUserList */
            $destUserList = UserMapper::getInstance()->findByColumn('id', $toUserId);
            if (count($destUserList) === 0) {
                $this->getLogger()->error(sprintf('user #%d does not exist - aborting.', $toUserId));
                exit(0);
            }
        } elseif (isset($roleId)) {
            // Load role
            try {
                $role = RoleMapper::getInstance()->findById($roleId);
            } catch (MapperException $e) {
                $this->getLogger()->error(sprintf('Error: role #%s not found', $roleId));
                exit(1);
            }

            // Load user list - abort if there are no users in list
            /** @var User[] $destUserList */
            $destUserList = UserMapper::getInstance()->findByColumn('group_id', $roleId);
            if (count($destUserList) === 0) {
                $this->getLogger()->info(sprintf(
                    "role #%d (%s) has no users - aborting.",
                    $roleId,
                    $role->getName()
                ));
                exit(0);
            }
        } else {
            $this->getLogger()->error('Error: either -r or -U options must be provided');
            exit(1);
        }


        // Pre-load the component list for each page
        /** @var SitePage $sitepage */
        $sitepage       = $this->getDi()->get('sitePage');
        $factory        = new Component_Eportfolio_Factory($user, $sitepage);
        $eportfolioList = [];
        foreach (Eportfolio\PageMapper::getInstance()->findPagesWithPermissions($userId) as $page) {
            // Ignore deleted pages
            if ($page->isDeleted()) {
                continue;
            }
            // ignore this page if it isn't page X
            if (isset($importPageId) && $importPageId != $page->getPageId()) {
                $this->getLogger()->info(sprintf("Not importing page #%d (%s)", $page->getPageId(), $page->getPageTitle()));
                continue;
            }
            $eportfolioList[] = [$page, $factory->buildComponentList($page->getPageId())];
        }
        if (empty($eportfolioList)) {
            $this->getLogger()->info(sprintf(
                "user #%d (%s) has no ePortfolio pages - aborting.",
                $userId,
                $user->formatName()
            ));
            exit(0);
        }

        // We have something to import, so proceed
        if ($role) {
            $this->getLogger()->info(sprintf(
                "importing %d eportfolio pages from user #%d (%s) into %d users in role #%d (%s).",
                count($eportfolioList),
                $userId,
                $user->formatName(),
                count($destUserList),
                $roleId,
                $role->getName()
            ));
        } else {
            $this->getLogger()->info(sprintf(
                "importing %d eportfolio pages from user #%d (%s) into user #%d.",
                count($eportfolioList),
                $userId,
                $user->formatName(),
                $toUserId
            ));
        }
        foreach ($destUserList as $destUser) {
            $destUserId = $destUser->getId();
            if ($destUserId == $userId) {
                $this->getLogger()->info("  skipping import from user into same user...");
            } else {
                $this->getLogger()->info(sprintf("  importing into user #%d (%s)...", $destUser->getId(), $destUser->formatName()));
                // Does this user have eportfolio disabled? If so then enable it
                if (!($destUser->getEportfolioEnable())) {
                    if ($enableEportfolio) {
                        if ($dryRun) {
                            $this->getLogger()->info("    ePortfolio is disabled - would enable...");
                        } else {
                            $this->getLogger()->info("    ePortfolio is disabled - enabling...");
                            $destUser->setEportfolioEnable(true);
                            UserMapper::getInstance()->save($destUser);
                        }
                    } else {
                        $this->getLogger()->info("    skipping because ePortfolio is disabled and --enable-eportfolio is not on.");
                        continue;
                    }
                }
                foreach ($eportfolioList as [$page, $componentList]) {
                    /** @var Eportfolio\Page $page */
                    /** @var Component_ControllerAbstract[] $componentList */

                    if ($skipDuplicates) {
                        // Check this user doesn't already have this page.
                        $existingPageList = Eportfolio\PageMapper::getInstance()->findByColumnList(
                            [
                                'uid'        => $destUserId,
                                'page_title' => $page->getPageTitle(),
                            ]
                        );
                        if (count($existingPageList) > 0) {
                            $this->getLogger()->info(sprintf('    skipping page "%s" as it already exists.', $page->getPageTitle()));
                            continue;
                        }
                    }
                    if ($dryRun) {
                        $this->getLogger()->info(sprintf('    would import page "%s"...', $page->getPageTitle()));
                        continue;
                    }
                    $this->getLogger()->info(sprintf('    importing page "%s"...', $page->getPageTitle()));

                    // Create a new ePortfolio page.
                    // Does this user already have a page of this name? Skip unless prompted otherwise
                    $newPage = clone $page;
                    $newPage->setOwner(UserMapper::getInstance()->createModel($destUserId));
                    Eportfolio\PageMapper::getInstance()->create($newPage);

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
                        foreach ($newInstance->getConfig() as $configDefinition) {
                            /** @var Component_Config $config */
                            /** @var Component_ConfigDefinition $configDefinition */
                            $config = new Component_Config();
                            $config
                                ->setConfigDefinitionId($configDefinition->getId())
                                ->setInstanceId($newInstance->getId())
                                ->setValue($configDefinition->getValue());
                            Component_ConfigMapper::getInstance()->create($config);
                        }

                        // Clone a new textbox for any textbox components
                        if ($component instanceof Component_Eportfolio_TextboxController) {
                            try {
                                /** @var Component\Eportfolio\Textbox $textbox */
                                $textbox    = Component\Eportfolio\TextboxMapper::getInstance()->findOneByColumn(
                                    'component_instance_id',
                                    $component->getInstance()->getId()
                                );
                                $newTextbox = clone $textbox;
                                $newTextbox->setComponentInstanceId($newInstance->getId());
                                Component\Eportfolio\TextboxMapper::getInstance()->create($newTextbox);
                            } catch (MapperException $e) {
                                // This component has no textbox yet, that's okay.
                            }
                        }
                    }

                    // Copy only the allow parents permission.
                    $parentPermission = 0;
                    foreach (\Eportfolio\PermissionMapper::getInstance()->findParentSetting($page->getPageId()) as $perm) {
                        $parentPermission = $perm->getValue();
                    }
                    \Eportfolio\PermissionMapper::getInstance()->updatePermissions(
                        $newPage->getPageId(),
                        $parentPermission,
                        [],
                        []
                    );

                    // Below code will copy all permissions, including perms for users
                    // and groups the destination user can't access.
//                $newPermissions = new \Schoolbox\Collection();
//                foreach ($page->getPermissions() as $permission) {
//                    /** @var Eportfolio\Permission $newPermission */
//                    $newPermission = clone $permission;
//                    $newPermission->setPageId($newPage->getPageId());
//                    $newPermissions->add($newPermission);
//                }
//                \Eportfolio\PermissionMapper::getInstance()->create($newPermissions);
//                \Eportfolio\PermissionMapper::getInstance()->updatePermissions($newPermissions);
                }
            }
        }
    }


    /**
     * Echos a help message to STDOUT
     */
    private function renderHelpMessage(): void
    {
        echo <<<HELP
Copies ePortfolios from a single user into either another user, or all users
with a given role.

Usage: import.php -u[fromUserId] [-U[toUserId]] [-r[toRoleId]] [--page[pageId]]
                  [--dry-run] [--skip-duplicates] [--enable-eportfolio]
    -u:                  the ID of the user to copy ePortfolios from.
    -U:                  the ID of the user to copy ePortfolios into
                         Either this or -r must be set.
    -r:                  the ID of the role to copy ePortfolios into
    --page:              if set, import only this page ID
    --dry-run:           if set, don't actually update anything, but show what
                         would be updated.
    --skip-duplicates:   if set, skips copying an ePortfolio if the destination
                         user already has an ePortfolio page with this name.
    --enable-eportfolio: if set, enables ePortfolio for destination users which
                         have ePortfolios disabled, then proceeds with copy.
                         Default behavior skips copy for users which have
                         ePortfolio disabled.

HELP;
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_ERRORS => E_ALL]);
$kernel->runApp();
