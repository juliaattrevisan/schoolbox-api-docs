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
    private $dbUser;

    /** @var string */
    private $dbPassword;

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

        $dbName = 'canvas_data_' . INSTANCE_ID;
        $dbHost = SQL_host;
        $dbCredentialFile = '/root/.my.cnf';

        // Check whether credential is provided by option
        $credentialProvided = $this->parseAppOptions();

        $this->parseMySqlConfig();

        // Use .my.cnf, if the file is accessible and credential is not provided.
        if (!$credentialProvided && file_exists($dbCredentialFile)) {
            $op = "--defaults-extra-file={$dbCredentialFile}";
        } else {
            if (!$credentialProvided) {
                $this->promptForCredential();
            }
            $op = "-u {$this->dbUser} -p{$this->dbPassword} -h {$dbHost}";
        }

        // Check whether the database already exists.
        $rootDbConnection = new \Alaress\Database\Connector\Base(new \Alaress\Database\Broker\Mysql\Broker());
        try {
            $result = $rootDbConnection->connect($dbHost, $dbName, $this->dbUser, $this->dbPassword);
            $this->getLogger()->info("Database {$dbName} already exists.");
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        } catch (\Alaress\Database\Exception $e) {
            // Expected
        }

        if ($this->perform()) {
            // Create database.
            passthru("mysql {$op} -e \"CREATE DATABASE {$dbName} CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';\"", $result);
            $this->getLogger()->info("Database {$dbName} has been created.");
        } else {
            $this->getLogger()->info("Creating a database {$dbName}");
        }
    }

    /**
     * Parses options and returns whether all options are valid
     *
     * @return bool
     */
    private function parseAppOptions(): bool
    {
        $this->dbUser = $this->getOpt(self::OPT_LONG_DB_USER);
        $this->dbPassword = $this->getOpt(self::OPT_LONG_DB_PASSWORD);
        return ($this->dbUser && $this->dbPassword);
    }

    /**
     * Prompts to get database credential
     *
     * @return void
     */
    private function promptForCredential(): void
    {
        $this->dbUser = $this->readLine('Enter a database username: ');
        if (!$this->dbUser) {
            $this->getLogger()->error('A database username must be provided');
            exit(1);
        }

        $this->dbPassword = $this->readLine('Enter a database password: ');
        if (!$this->dbPassword) {
            $this->getLogger()->error('A database password must be provided');
            exit(1);
        }
    }

    /**
     * Parse config file
     */
    private function parseMySqlConfig(): void
    {
        $dbCredentialFile = '/root/.my.cnf';
        $handle = fopen($dbCredentialFile, 'r');
        while ($line = fgets($handle)) {
            $conf = explode('=', $line);
            if (\count($conf) != 2) {
                continue;
            }
            if (trim($conf[0]) === 'user') {
                $this->dbUser = trim($conf[1]);
                continue;
            }
            if (trim($conf[0]) === 'password') {
                $this->dbPassword = trim($conf[1]);
                continue;
            }
        }
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
        
    $yellow--db-user=DB_USER$white
        Username to access schoolbox database
        
    $yellow--db-password=DB_PASSWORD$white
        Password to access schoolbox database
";
        \fwrite(STDOUT, $help);
    }


}, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
