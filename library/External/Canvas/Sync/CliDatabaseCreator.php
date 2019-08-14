<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Alaress\Database\Connector\Base;
use Alaress\Database\Broker\Mysql\Broker;

/**
 * Command line application for syncing canvas-data
 */
class CliDatabaseCreator extends CliTool\App
{
    use CanvasDatabaseTrait;

    private const OPT_SHORT_HELP       = 'h';                 // print help
    private const OPT_LONG_HELP        = 'help';              // print help
    private const OPT_LONG_DB_USER     = 'db-user:';
    private const OPT_LONG_DB_PASSWORD = 'db-password:';

    /** @var string */
    private $dbRootUser;

    /** @var string */
    private $dbRootPassword;

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
        $canvasDb = $this->getCanvasDbName();
        $dbSchoolboxUser = SQL_user;
        $dbCredentialFile = '/root/.my.cnf';

        // Check whether credential is provided by option
        if (!$this->parseAppOptions() && !$this->parseMySqlConfig($dbCredentialFile)) {
            $this->promptForCredential();
        }

        // Check whether the credential is correct by connecting to schoolbox database.
        $rootDbConnection = new Base(new Broker());
        try {
            $result = $rootDbConnection->connect($this->dbHost, SQL_db, $this->dbRootUser, $this->dbRootPassword);
        } catch (\Alaress\Database\Exception $e) {
            $this->getLogger()->warning('Could not connect to schoolbox db using the credentials provided: ' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        // Check whether the database already exists.
        try {
            $result = $rootDbConnection->connect($this->dbHost, $canvasDb, $this->dbRootUser, $this->dbRootPassword);
            $this->getLogger()->info("Database {$canvasDb} already exists.");
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        } catch (\Alaress\Database\Exception $e) {
            // Expected
        }

        if ($this->perform()) {
            # Vagrant and on-premises use 'localhost'. Cloud uses '%' wildcard as PHP and MySQL are on different hosts
            # ORDER BY to prefer '%' over 'localhost' if both are available (they shouldn't be)
            $sql = "SELECT Host FROM mysql.user WHERE User = '{$dbSchoolboxUser}' ORDER BY Host LIMIT 1";
            exec("mysql -u {$this->dbRootUser} -p{$this->dbRootPassword} -h {$this->dbHost} --silent --skip-column-names -e \"{$sql}\" 2>&1", $out, $result);
            if ($result || !count($out)) {
                $this->getLogger()->warning("Error fetching allowed host for {$dbSchoolboxUser}:" . implode(',', $out));
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }
            $allowedHost = $out[0];

            // Create database.
            exec("mysql -u {$this->dbRootUser} -p{$this->dbRootPassword} -h {$this->dbHost} -e \"CREATE DATABASE {$canvasDb} CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';\" 2>&1", $out, $result);
            if ($result !== CliKernelAbstract::EXIT_STATUS_OK) {
                $this->getLogger()->warning("Error creating database {$canvasDb}:" . implode(',', $out));
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }
            $this->getLogger()->info("Database {$canvasDb} has been created.");

            // Grant permissions to schoolbox user to new database
            exec("mysql -u {$this->dbRootUser} -p{$this->dbRootPassword} -h {$this->dbHost} -e \"GRANT ALL ON {$canvasDb}.* to '{$dbSchoolboxUser}'@'{$allowedHost}';\" 2>&1", $out, $result);
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
        $file = __FILE__;

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
}
