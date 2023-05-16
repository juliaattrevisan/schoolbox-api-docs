<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Migrate puppet config's for select school codes to their selected default.
All other schools, will get the system default.
HELP;
            echo "\nUsage: php " . __FILE__ . "\n\n";
            exit;
        }

        $dbc    = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        try {
            $schoolCode       = $dbc->executeQuery("SELECT value FROM config WHERE name='site_school_code'")->fetch()['value'];
        } catch (Exception $e) {
            $logger->info("Could not find school code.  Stopping.\n");
            die;
        }

        // set setting for schools that had the option set in puppet
        $disable = ['sthprt', 'glacc'];

        $logger->info("Found school code '$schoolCode'");
        if (in_array($schoolCode, $disable)) {
            $logger->info("Disabling web crawlers for school");
            $statement = $dbc->executeQuery(
                "UPDATE config SET
                  value = '0'
                WHERE name = 'web_crawlers_enabled'"
            );
        }
    }

    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
