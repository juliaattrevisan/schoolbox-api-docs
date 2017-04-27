<?php

/** @var \Schoolbox\Application\WebRequest\Kernel $kernel */
$kernel = require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\ICommunityPortal;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;

$page = new SitePage();
$userHandler = new User();
$groupHandler = new Group();
$configMapper = ConfigMapper::getInstance();

if (!in_array($_SESSION['gid'], $groupHandler->canChangePassword()) && !$_SESSION['su']) {
    error(19);
}

if (isset($_POST['current_password'])) {
    if (empty($_POST['current_password'])) {
        error(105, ['message' => "You must enter your current password."]);
    }

    if ($_POST['new_password'] != $_POST['confirm_password']) {
        error(105, ['message' => "The new and confirmation passwords you entered do not match."]);
    }

    if (mb_strlen($_POST['new_password'], 'UTF-8') < 8) {
        error(105, ['message' => "Your new password must be at least 8 characters long."]);
    }

    try {
        /** @var Schoolbox\User $user */
        $user                = $_SESSION['user'];
        $userMapper          = Schoolbox\UserMapper::getInstance();
        $passwordService     = new \Alaress\Password\Service();
        $passwordMailService = new \Schoolbox\User\Password\MailService($configMapper);
        // Test password against existing
        $currentHash = $user->getPassword();
        if ($passwordService->verify($_POST['current_password'], $currentHash)
            || $userMapper->hashPassword($_POST['current_password']) == $currentHash
        ) {
            $user
                ->setRetrievablePassword($_POST['new_password'])
                ->setPassword($passwordService->hash($_POST['new_password']));
            $userMapper->save($user);
            $_SESSION['password'] = $_POST['new_password'];
        } else {
            $userHandler->logUserPasswordChange($user->getUsername(), 'change_pwd_fail', 'warn');
            $passwordMailService->sendPasswordChangeFailureEmail($user);
            error(105, ['message' => "Your current password did not match what is stored in the database."]);
            exit;
        }

        // Try this
        // I shouldn't have to send all those damn password change mails anymore either
        $redirect = '/account/modify/success';
        if ($user->getRole()->isParent()) {

            /** @var \Alaress\Database\Connector\IConnector $databaseExternal */
            $databaseExternal = $kernel->getDependencyInjector()->get('databaseExternal');

            $factory = new ExternalConnectorFactory();

            /** @var ICommunityPortal $communityPortal */
            $communityPortal = $factory->build(
                $configMapper,
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_COMMUNITY_PORTAL
            );

            try {
                $communityPortal->changePassword($user->getExternalId(), $_POST['new_password']);
            } catch (\UnexpectedValueException $e) {
                // Portal integration wasn't set up properly?
                // Warn the user, but don't stop password from getting set.
                $redirect = '/account/modify/success/portalPasswordNotSet';
            }
        }

        $userHandler->logUserPasswordChange($user->getUsername(), 'email_change_pwd', 'info');
        $passwordMailService->sendChangePasswordEmail($user);
        $_SESSION['password'] = $_POST['new_password'];

        SitePage::localRedir($redirect);
    } catch (Exception $e) {
        error(105, ['message' => $e->getMessage()]);
    }
}
