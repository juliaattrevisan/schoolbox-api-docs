#!/usr/bin/php
<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;
use Schoolbox\UserMapper;
use Alaress\Password;
use Schoolbox\Mapper;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Reset a user's password.
 */
$app = new class($argv[1] ?? '') extends CliTool\App
{

    /** @var string */
    private $username;


    /** @param string $username */
    public function __construct(string $username)
    {
        $this->username = $username;
    }


    public function run(): void
    {
        $username        = $this->getUserName();
        $userMapper      = UserMapper::getInstance();
        $passwordService = new Password\Service();

        try {
            $user = $userMapper->findOneByColumn('username', $username);
        } catch (Mapper\Exception $e) {
            $this->getLogger()->error(sprintf('No user with username "%s" exists.', $username));
            exit(1);
        }
        $this->getLogger()->info(sprintf('Resetting password for user "%s".', $username));

        // Ask for password
        $password = trim($this->readPassword("Enter new Schoolbox password: "));
        $confirm  = trim($this->readPassword("Retype new Schoolbox password: "));

        if ($password !== $confirm) {
            $this->getLogger()->error('Passwords do not match - password unchanged');
            exit(1);
        }

        $user
            ->setRetrievablePassword($password)
            ->setPassword($passwordService->hash($password));
        if (!$userMapper->save($user)) {
            $this->getLogger()->error(sprintf('Password for user "%s" could not be reset for some reason!', $username));
            exit(1);
        }
        $this->getLogger()->info(sprintf('Password for user "%s" has been reset.', $username));
        exit(0);
    }


    /**
     * Returns the username of the user whose password we will update
     *
     * @return string
     */
    private function getUserName(): string
    {
        // User CLI option if provided
        if ($this->username) {
            return $this->username;
        }

        // Prompt if not provided
        $this->username = $this->readLine('Enter a schoolbox username: ');
        if ($this->username) {
            return $this->username;

        }
        $this->getLogger()->error('A username must be provided');
        exit(1);
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();

