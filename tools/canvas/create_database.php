#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Creates a local canvas staging database named canvas_data_[INSTANCE_ID].
 *
 * This can be run as root or non-root
 * This attempts to connect to database host using credentials at /root/.my.cnf.
 * If these cannot be read (either lack perm, or file not exists), this will prompt the user for them.
 * Database host is the same as schoolbox database.
 * Tool grants all perms on this database to the schoolbox user (eg schoolbox or schoolboxdev)
 * If database already exists, this warns user and exits, it does not overwrite it.
 */
$kernel = new CliTool\Kernel(new class extends CliTool\App
{
    private const OPT_SHORT_HELP       = 'h';                 // print help
    private const OPT_LONG_HELP        = 'help';              // print help
    private const OPT_LONG_DB_USER     = 'db-user:';
    private const OPT_LONG_DB_PASSWORD = 'db-password:';

    /** @var string */
    private $dbRootUser;

    /** @var string */
    private $dbRootPassword;

    /** @inheritdoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
        ];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_DB_USER,
            self::OPT_LONG_DB_PASSWORD,
        ];
    }

    /** @inheritdoc */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Information
        $canvasDb = 'canvas_data_' . INSTANCE_ID;
        $dbHost = SQL_host;
        $dbSchoolboxUser = SQL_user;
        $dbCredentialFile = '/root/.my.cnf';

        // Check whether credential is provided by option
        if (!$this->parseAppOptions() && !$this->parseMySqlConfig($dbCredentialFile)) {
            $this->promptForCredential();
        }

        // Check whether the credential is correct by connecting to schoolbox database.
        $rootDbConnection = new \Alaress\Database\Connector\Base(new \Alaress\Database\Broker\Mysql\Broker());
        try {
            $result = $rootDbConnection->connect($dbHost, SQL_db, $this->dbRootUser, $this->dbRootPassword);
        } catch (\Alaress\Database\Exception $e) {
            $this->getLogger()->warning('Could not connect to schoolbox db using the credentials provided: ' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        // Check whether the database already exists.
        try {
            $result = $rootDbConnection->connect($dbHost, $canvasDb, $this->dbRootUser, $this->dbRootPassword);
            $this->getLogger()->info("Database {$canvasDb} already exists.");
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        } catch (\Alaress\Database\Exception $e) {
            // Expected
        }

        if ($this->perform()) {
            // Create database.
            exec("mysql -u {$this->dbRootUser} -p{$this->dbRootPassword} -h {$dbHost} -e \"CREATE DATABASE {$canvasDb} CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';\" 2>&1", $out, $result);
            if ($result !== CliKernelAbstract::EXIT_STATUS_OK) {
                $this->getLogger()->warning("Error creating database {$canvasDb}:" . implode(',', $out));
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }
            $this->getLogger()->info("Database {$canvasDb} has been created.");
            exec("mysql -u {$this->dbRootUser} -p{$this->dbRootPassword} -h {$dbHost} -e \"GRANT ALL ON {$canvasDb}.* to {$dbSchoolboxUser};\" 2>&1", $out, $result);
            if ($result !== CliKernelAbstract::EXIT_STATUS_OK) {
                $this->getLogger()->warning("Error granting access to {$dbSchoolboxUser}:" . implode(',', $out));
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }
        } else {
            $this->getLogger()->info("Creating a database {$canvasDb}");
        }
    }

    /**
     * Parses options and returns whether all options are valid
     *
     * @return bool
     */
    private function parseAppOptions(): bool
    {
        $this->dbRootUser = $this->getOpt(self::OPT_LONG_DB_USER);
        $this->dbRootPassword = $this->getOpt(self::OPT_LONG_DB_PASSWORD);
        return ($this->dbRootUser && $this->dbRootPassword);
    }

    /**
     * Prompts to get database credential
     *
     * @return void
     */
    private function promptForCredential(): void
    {
        $this->dbRootUser = $this->readLine('Enter a database root username: ');
        if (!$this->dbRootUser) {
            $this->getLogger()->error('A database username must be provided');
            exit(1);
        }

        $this->dbRootPassword = $this->readLine('Enter a database password: ');
        if (!$this->dbRootPassword) {
            $this->getLogger()->error('A database password must be provided');
            exit(1);
        }
    }

    /**
     * Parses config file and sets db user and db password
     *
     * @param string $dbCredentialFile
     * @return bool
     */
    private function parseMySqlConfig(string $dbCredentialFile): bool
    {
        if (!is_readable($dbCredentialFile)) {
            return false;
        }
        $dbCredentials = \parse_ini_file($dbCredentialFile);
        if (!$dbCredentials) {
            return false;
        }
        $this->dbRootUser = $dbCredentials['user'];
        $this->dbRootPassword = $dbCredentials['password'];
        return $this->dbRootUser && $this->dbRootPassword;
    }

    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $file = $this->getToolFilename();

        $green = self::COLOUR_GREEN;
        $white = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = "$white

WHAT THIS DOES:
    Creates a local canvas staging database named canvas_data_[INSTANCE_ID].

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

OPTIONS:
    $yellow-h, --help$white
        Print this help and exit
        
    $yellow-p, --perform$white
        If set, updates DB - otherwise, performs a dry run
        
    $yellow--db-user=DB_USER$white
        Username to access schoolbox database
        
    $yellow--db-password=DB_PASSWORD$white
        Password to access schoolbox database
";
        \fwrite(STDOUT, $help);
    }


}, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
