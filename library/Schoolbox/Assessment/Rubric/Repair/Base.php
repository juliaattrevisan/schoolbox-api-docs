<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Psr\Log\LoggerInterface as Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Swift_SwiftException;

/**
 * Base class for rubric repair scripts.
 */
abstract class Base extends CliTool\App
{
    // This tools CLI options
    private const OPT_SHORT_FOLDER = 'f:';
    private const OPT_LONG_MAILTO = 'mailto:';


    // An indentation constant.
    // TODO make this part of the CliTool class
    private const INDENT = '  ';


    // Query for finding folders to repair.
    // TODO consider skipping course/unit specs: another script repairs those
    protected const FIND_FOLDERS_QUERY = '
       SELECT
           id, name
       FROM folder
       WHERE deleted_at IS NULL
    ';


    // Query for finding capabilities to repair within a folder.
    protected const FIND_CAPABILITIES_QUERY = "
        SELECT id, name
        FROM   rubric_group
        WHERE  parent_type = 'category'
        AND    parent_id   = :folderId
        AND    deleted_at  IS NULL
        ORDER BY name, id
    ";


    /**
     * Query for finding folders which may contain a capability.
     *
     * @var IQuery
     */
    protected $findFoldersQuery;


    /**
     * Query for finding rubric capabilities in a folder.
     *
     * @var IQuery
     */
    protected $findCapabilitiesQuery;


    /**
     * @var Logger
     */
    protected $log;


    /**
     * @var IConnector
     */
    protected $db;


    /**
     * @var Alaress_Date_Format
     */
    protected $now;


    /**
     * The fully-qualified domain name for this instance.
     *
     * @var
     */
    protected $fqdn;


    /**
     * The folders to merge duplicate capabilities for, if passed via the
     * `self::OPT_SHORT_FOLDER` option.
     *
     * If empty, merge for all folders.
     *
     * @var array
     */
    protected $folders;


    /**
     * Which capabilities have been fixed by this script?
     *
     * @var array|array[] key:   capability ID
     *                    value: array with keys "name", "folderId"
     */
    protected $fixedCapabilities = [];


    /**
     * Which capabilities were not able to be fixed?
     *
     * These must be fixed manually for each instance.
     *
     * @var array|array[] key:   capability ID
     *                    value: array with keys "name", "folderId"
     */
    protected $failedCapabilities = [];


    /**
     * Which indicators have been fixed by this script?
     *
     * @var array|array[] key:   indicator ID
     *                    value: array with keys "name", "folderId"
     */
    protected $fixedIndicators = [];


    /**
     * Which indicators have been fixed by this script?
     *
     * These must be fixed manually for each instance.
     *
     * @var array|array[] key:   indicator ID
     *                    value: array with keys "name", "folderId"
     */
    protected $failedIndicators = [];


    /**
     * Which due works have been fixed by this script?
     *
     * @var array|array[] key:   assessment ID
     *                    value: array with keys "name", "folderId"
     */
    protected $fixedDueWorks = [];


    /**
     * Which due works could not be fixed by this script?
     *
     * These must be fixed manually for each instance.
     *
     * @var array|array[] key:   assessment ID
     *                    value: array with keys "name", "folderId"
     */
    protected $failedDueWorks = [];


    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_FOLDER];
    }


    /**
     * @inheritDoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_MAILTO];
    }


    /**
     * Set up basic dependencies for all repair scripts.
     *
     * @throws ServiceNotFound
     */
    protected function setupDependencies(): void
    {
        // Install logger and DB
        $this->log     = $this->getLogger();
        $this->db      = $this->getDi()->get('databaseInternal');
        $this->now     = Alaress_Date_Format::now();
        $this->fqdn    = trim(
            $this->getConfig()->get('site_hostname') . '.' . $this->getConfig()->get('site_domain'),
            ';.'
        );
        $folderIds     = $this->getOpt(self::OPT_SHORT_FOLDER);
        $this->folders = empty($folderIds)
            ? []
            : array_map('intval', explode(',', $folderIds));
    }


    /**
     * Prepare queries that all scripts need.
     */
    private function setupCommonQueries(): void
    {
        $this->findFoldersQuery
            = $this->db->prepareQuery(self::FIND_FOLDERS_QUERY);
        $this->findCapabilitiesQuery
            = $this->db->prepareQuery(self::FIND_CAPABILITIES_QUERY);
    }


    /**
     * Set up and run the merge process.
     *
     * Install all instance vars, and wraps the merge process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     *
     * @throws ServiceNotFound
     */
    public function run(): void
    {
        $this->setupDependencies();
        $this->setupCommonQueries();
        $this->setupQueries();

        if ($this->perform()) {
            $this->log->info('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->info('Running in dry-run mode: use -p to write changes to database');
        }

        $this->repair();

        if ($this->perform()) {
            $this->log->alert('Changes written to database');
        } else {
            $this->log->alert('Dry-run complete - no changes written to database');
        }

        $this->summarizeResult();
    }


    /**
     * Build a mail sender.
     *
     * @param string $subject
     * @param array  $context
     * @return void
     * @throws Swift_SwiftException if the mail couldn't be sent
     */
    protected function sendMail(string $subject, array $context): void
    {
        $mailto = $this->getOpt(self::OPT_LONG_MAILTO);
        if ($mailto) {
            $mailer = new TwigEmail('output.twig');
            $mailer->setCoreContext((new CoreContext($this->getConfig()))->getContext());
            $mailer->setFrom($this->getConfig()->get('mail_from_address'));
            $mailer->setSubject($subject);
            $mailer->setContext($context);
            $mailer->send($this->getOpt(self::OPT_LONG_MAILTO));
        }
    }


    /**
     * Helper method for indenting log output for legibility.
     *
     * @param int $depth The depth to indent by
     * @return string
     */
    protected static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }


    /**
     * Prepare any queries this script may need.
     */
    protected abstract function setupQueries(): void;


    /**
     * Actually run the repair. Run after queries are set up.
     */
    protected abstract function repair(): void;


    /**
     * Output a summary of the results of this script.
     */
    protected abstract function summarizeResult(): void;
}
