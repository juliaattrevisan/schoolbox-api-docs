#!/usr/bin/php
<?php
/**
 * Reset a user's password.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../include/Bootstrap.php';

if (!isset($argv[1])) {
    fwrite(STDERR, "A username must be provided.\n");
    exit(1);
} else {
    $username = $argv[1];
}

$userMapper      = \Schoolbox\UserMapper::getInstance();
$passwordService = new \Alaress\Password\Service();
try {
    $user = $userMapper->findOneByColumn('username', $username);
} catch (\Schoolbox\Mapper\Exception $e) {
    fwrite(STDERR, "No user with username \"$username\" exists.\n");
    exit(1);
}

echo "Resetting password for user \"$username\".\n";

// Ask for password
// Switch off echo
system('stty -echo');
echo "Enter new Schoolbox password: ";
$password = trim(fgets(STDIN));
echo "\nRetype new Schoolbox password: ";
$confirm  = trim(fgets(STDIN));
system('stty echo');

if ($password !== $confirm) {
    fwrite(STDERR, "Passwords do not match - password unchanged\n");
    exit(1);
}

/** @var \Schoolbox\User $user */
$user
    ->setRetrievablePassword($password)
    ->setPassword($passwordService->hash($password));
if ($userMapper->save($user)) {
    echo "Password for user \"$username\" has been reset.\n";
} else {
    echo "Password for user \"$username\" could not be reset for some reason!\n";
    exit(1);
}
exit(0);
