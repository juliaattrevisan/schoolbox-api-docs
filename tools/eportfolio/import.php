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
    echo "Usage: import.php -u[userId] -r[roleId] [--skip-duplicates]\n";
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

// Load users and pages
/** @var User[] $destUserList */
/** @var Eportfolio\Page[] $eportfolioPageList */
$destUserList       = UserMapper::getInstance()->findByColumn('group_id', $roleId);
$eportfolioPageList = Eportfolio\PageMapper::getInstance()->findPagesWithPermissions($userId);
printf(
    "importing %d eportfolio pages from user #%d (%s) for %d users in role #%d (%s).\n",
    count($eportfolioPageList),
    $userId,
    $user->formatName(),
    count($destUserList),
    $roleId,
    $role->getName()
);
if (count($destUserList) === 0 || count($eportfolioPageList) === 0) {
    exit;
}

// Pre-load the component list for each page
$factory        = new Component_Eportfolio_Factory($user, new SitePage(false));
$eportfolioList = array();
foreach ($eportfolioPageList as $page) {
    $eportfolioList[] = array($page, $factory->buildComponentList($page->getPageId()));
}

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
                echo "    ePortfolio is disabled - enabling...";
                $destUser->setEportfolioEnable(true);
                UserMapper::getInstance()->save($destUser);
            }
            else {
                echo "    skipping because ePortfolio is disabled and --no-enable-eportfolio is on.";
                break;
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
