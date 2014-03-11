<?php
/**
 * Imports eportfolio page layouts from a user to all users within a group.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Mapper\Exception as MapperException;

require_once __DIR__ . '/../../include/Bootstrap.php';

$options          = getopt('u:r:', array('skip-duplicates', 'enable-eportfolio'));
$userId           = $options['u'];
$roleId           = $options['r'];
$skipDuplicates   = isset($options['skip-duplicates']);
$enableEportfolio = isset($options['enable-eportfolio']);
if (!(isset($userId) && isset($roleId))) {
    echo <<<HELP
Copies ePortfolios from a single user into all users with a given role.

Usage: import.php -u[userId] -r[roleId] [--skip-duplicates] [--no-enable-eportfolio]
    -u:                  the ID of the user to copy ePortfolios from
    -r:                  the ID of the role to copy ePortfolios into
    --skip-duplicates:   if set, skips copying an ePortfolio if the destination
                         user already has an ePortfolio page with this name.
    --enable-eportfolio: if set, enables ePortfolio for destination users which
                         have ePortfolios disabled, then proceeds with copy.
                         Default behavior skips copy for users which have
                         ePortfolio disabled.

HELP;
    exit(1);
}

// Load source user
try {
    /** @var User $user */
    $user = UserMapper::getInstance()->findById($userId);
}
catch (MapperException $e) {
    fprintf(STDERR, "Error: user #%s not found\n", $userId);
    exit(1);
}

// Load role
try {
    /** @var Role $role */
    $role = RoleMapper::getInstance()->findById($roleId);
}
catch (MapperException $e) {
    fprintf(STDERR, "Error: role #%s not found\n", $roleId);
    exit(1);
}

// Load user list - abort if there are no users in list
/** @var User[] $destUserList */
$destUserList = UserMapper::getInstance()->findByColumn('group_id', $roleId);
if (count($destUserList) === 0) {
    printf(
        "role #%d (%s) has no users - aborting.\n",
        $roleId,
        $role->getName()
    );
    exit;
}

// Pre-load the component list for each page
$factory        = new Component_Eportfolio_Factory($user, new SitePage(false));
$eportfolioList = array();
foreach (Eportfolio\PageMapper::getInstance()->findPagesWithPermissions($userId) as $page) {
    /** @var Eportfolio\Page $page */
    // Ignore deleted pages
    if ($page->isDeleted()) {
        continue;
    }
    $eportfolioList[] = array($page, $factory->buildComponentList($page->getPageId()));
}
if (empty($eportfolioList)) {
    printf(
        "user #%d (%s) has no ePortfolio pages - aborting.\n",
        $userId,
        $user->formatName()
    );
    exit;
}

// We have something to import, so proceed
printf(
    "importing %d eportfolio pages from user #%d (%s) for %d users in role #%d (%s).\n",
    count($eportfolioList),
    $userId,
    $user->formatName(),
    count($destUserList),
    $roleId,
    $role->getName()
);
foreach ($destUserList as $destUser) {
    $destUserId = $destUser->getId();
    if ($destUserId == $userId) {
        print("  skipping import from user into same user...\n");
    }
    else {
        printf("  importing into user #%d (%s)...\n", $destUser->getId(), $destUser->formatName());
        // Does this user have eportfolio disabled? If so then enable it
        if (!($destUser->getEportfolioEnable())) {
            if ($enableEportfolio) {
                print("    ePortfolio is disabled - enabling...\n");
                $destUser->setEportfolioEnable(true);
                UserMapper::getInstance()->save($destUser);
            }
            else {
                print("    skipping because ePortfolio is disabled and --enable-eportfolio is not on.\n");
                continue;
            }
        }
        foreach ($eportfolioList as $eportfolio) {
            /** @var Eportfolio\Page $page */
            /** @var Component_ControllerAbstract[] $componentList */
            list($page, $componentList) = $eportfolio;

            if ($skipDuplicates) {
                // Check this user doesn't already have this page.
                $existingPageList = Eportfolio\PageMapper::getInstance()->
                    findByColumnList(
                        array(
                            'uid'        => $destUserId,
                            'page_title' => $page->getPageTitle(),
                        )
                    );
                if (count($existingPageList) > 0) {
                    printf("    skipping page \"%s\" as it already exists.\n", $page->getPageTitle());
                    continue;
                }
            }
            printf("    importing page \"%s\"...\n", $page->getPageTitle());

            // Create a new ePortfolio page.
            // Does this user already have a page of this name? Skip unless prompted otherwise
            $newPage = clone $page;
            $newPage->setUid($destUserId);
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
                        $textbox = Component\Eportfolio\TextboxMapper::getInstance()->findOneByColumn(
                            'component_instance_id', $component->getInstance()->getId()
                        );
                        $newTextbox = clone $textbox;
                        $newTextbox->setComponentInstanceId($newInstance->getId());
                        Component\Eportfolio\TextboxMapper::getInstance()->create($newTextbox);
                    }
                    catch (MapperException $e) {
                        // This component has no textbox yet, that's okay.
                    }
                }
            }
        }
    }
}
exit;
