<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Alaress_Date_Format;
use OutOfRangeException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface as Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Mail\TwigEmailFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Base class for rubric repair scripts.
 */
abstract class Base extends CliTool\App
{
    // This tools CLI options
    private const OPT_SHORT_FOLDER = 'f:';
    private const OPT_LONG_MAILTO = 'mailto:';
    private const OPT_LONG_NO_TRANSACTION = 'no-transaction';


    // An indentation constant.
    // TODO make this part of the CliTool class
    private const INDENT = '  ';


    // Query for validating whether this should even run.
    private const VERIFY_RUNNABLE_QUERY = "SHOW TABLES LIKE 'rubric_capability'";


    // Query for finding folders to repair.
    // Don't repair course spec or unit spec folders: there's already a script
    // for those (tools/rubric/repairSpecAssessments.php)
    protected const FIND_FOLDERS_QUERY = '
        SELECT DISTINCT
            f.id,
            f.name
        FROM      folder                    f
        LEFT JOIN course_specification      cs  ON f.id = cs.folder_id
        LEFT JOIN course_unit_specification cus ON f.id = cus.course_specification_id
        WHERE f.deleted_at IS NULL
        AND   (cs.id  IS NULL OR cs.deleted_at  IS NOT NULL)
        AND   (cus.id IS NULL OR cus.deleted_at IS NOT NULL)
        ORDER BY f.id
    ';


    // Query for finding capabilities to repair within a folder.
    protected const FIND_CAPABILITIES_QUERY_V1 = "
        SELECT id, name
        FROM   rubric_group
        WHERE  parent_type = 'category'
        AND    parent_id   = :folderId
        AND    deleted_at  IS NULL
        ORDER BY name, id
    ";
    protected const FIND_CAPABILITIES_QUERY_V2 = "
        SELECT id, name
        FROM   rubric_capability
        WHERE  parent_type = 'category'
        AND    parent_id   = :folderId
        AND    deleted_at  IS NULL
        ORDER BY name, id
    ";


    // If a capability or indicator is more than this many characters:
    // truncate it to this width.
    protected const MAX_LOG_ITEM_WIDTH = 50;


    /**
     * Should the v1 or v2 queries be used?
     *
     * @var bool
     */
    protected $useV2Queries;


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
     * What number savepoint are we up to?
     *
     * Static because the savepoints must be maintained per DB connection, of
     * which there's only one per script execution.
     *
     * @var int
     */
    private static $savepointNumber = 0;


    /**
     * What are the current savepoints in this connection?
     *
     * Static because the savepoints must be maintained per DB connection, of
     * which there's only one per script execution.
     *
     * @var array|string[]
     */
    private static $savepointStack = [];


    /**
     * @var Alaress_Date_Format
     */
    protected $now;


    /**
     * The fully-qualified domain name for this instance.
     *
     * @var string
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
     * Determines whether v2 queries are used.
     *
     * @return bool
     */
    private function determineV2QueryUsage(): bool
    {
        $this->log->notice('Checking `rubric_capability` table exists...');
        $query = $this->db->prepareQuery(self::VERIFY_RUNNABLE_QUERY);
        $query->execute();
        if ($query->rowCount() > 0) {
            $this->log->notice('`rubric_capability` table exists: running with v2 queries');
            return true;
        }
        $this->log->notice('`rubric_capability` table does not exist: running with v1 queries');
        return false;
    }


    /**
     * Set up basic dependencies for all repair scripts.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
            = $this->db->prepareQuery(
                $this->useV2Queries
                    ? self::FIND_CAPABILITIES_QUERY_V2
                    : self::FIND_CAPABILITIES_QUERY_V1
            );
    }


    /**
     * Set up and run the merge process.
     *
     * Install all instance vars, and wraps the merge process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     */
    public function run(): void
    {
        $this->setupDependencies();
        $this->useV2Queries = $this->determineV2QueryUsage();
        $this->setupCommonQueries();
        $this->setupQueries();

        $this->outputIntro();

        if ($this->perform()) {
            $this->log->info('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->info('Running in dry-run mode: use -p to write changes to database');
        }

        if (!empty($this->folders)) {
            $this->log->info('Restricting to folders: ' . implode(',', $this->folders));
        }

        // Is this wrapped in a transaction?
        $transaction = !(bool)$this->getOpt(self::OPT_LONG_NO_TRANSACTION);
        if ($transaction) {
            $this->db->beginTransaction();
        }

        $this->log->notice('-----');
        $this->repair();
        $this->log->notice('-----');
        $this->outputSummary();
        $this->log->notice('-----');


        // Did this script do nothing? Report differently
        $isAnythingChanged =
            count($this->failedIndicators) > 0
            || count($this->failedCapabilities) > 0
            || count($this->failedDueWorks) > 0
            || count($this->fixedIndicators) > 0
            || count($this->fixedCapabilities) > 0
            || count($this->fixedDueWorks) > 0
        ;

        if ($this->perform()) {
            if ($transaction) {
                $this->db->commit();
            }
            if ($isAnythingChanged) {
                $this->log->warning('Changes written to database');
            } else {
                $this->log->notice('No changes needed to be written');
            }
        } else {
            if ($transaction) {
                $this->db->rollBack();
            }
            if ($isAnythingChanged) {
                $this->log->warning('Dry-run complete - changes would be written to database');
            } else {
                $this->log->notice('Dry-run complete - no changes would have needed to be written');
            }
        }
    }


    /**
     * Build and send an email.
     *
     * @param string $template
     * @param string $subject
     * @param array  $context
     */
    protected function sendMail(string $template, string $subject, array $context): void
    {
        $mailto = $this->getOpt(self::OPT_LONG_MAILTO);
        if ($mailto) {
            $twigEmailFactory = new TwigEmailFactory($this->getConfig(), $this->log);
            $mailer = $twigEmailFactory->build($template, $subject, $mailto);
            $mailer->setContext($context);
            try {
                $mailer->send();
            } catch (TransportExceptionInterface $e) {
                $this->log->error(sprintf('Could not send mail to "%s"', $mailto));
            }
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
     * Truncate `$string` to `$length` characters.
     *
     * @param string $string
     * @param int    $length
     * @param string $trailing If `$string` is truncated, trail it with this.
     * @return string
     */
    protected static function truncate($string, int $length, string $trailing = '...'): string
    {
        // Apparently `$string` needn't be a string?
        $string   = (string)$string;
        $encoding = 'UTF-8';
        if (mb_strlen($string, $encoding) > $length) {
            return mb_substr($string, 0, $length - mb_strlen($trailing, $encoding), $encoding) . $trailing;
        }
        return $string;
    }


    /**
     * Prepare any queries this script may need.
     */
    abstract protected function setupQueries(): void;


    /**
     * Actually run the repair. Run after queries are set up.
     */
    abstract protected function repair(): void;


    /**
     * Output a summary of what this script will do.
     */
    abstract protected function outputIntro(): void;


    /**
     * Generate a savepoint name, and add it to the stack.
     *
     * @return string
     */
    private function generateSavepointName(): string
    {
        self::$savepointStack[] = $savepoint = 'savepoint' . self::$savepointNumber++;
        return $savepoint;
    }


    /**
     * Retrieve the most recent savepoint added to the stack.
     *
     * @return string
     * @throws OutOfRangeException if there's no savepoints in the stack
     */
    private function retrieveLastSavepointName(): string
    {
        if (empty(self::$savepointStack)) {
            throw new OutOfRangeException('No savepoints');
        }
        return array_pop(self::$savepointStack);
    }


    /**
     * Create a savepoint, and adds it to the stack.
     *
     * Required because nested transactions don't exist in MySQL.
     *
     * @todo replace with nested transaction emulation, if/when SA-6405 is in
     *
     * @return bool
     */
    protected function createSavepoint(): bool
    {
        // You'll just have to trust there's no SQL injection here:
        // I can't bind inputs to a query returned via $this->db->prepareQuery.
        return $this->db->execute('SAVEPOINT ' . $this->generateSavepointName());
    }


    /**
     * Commit all changes since the last savepoint.
     *
     * This removes the last savepoint from the stack that `createSavepoint`
     * added.
     *
     * Required because nested transactions don't exist in MySQL.
     *
     * @return bool
     * @throws OutOfRangeException if there's no savepoints in the stack
     *
     */
    protected function commitSavepoint(): bool
    {
        // You'll just have to trust there's no SQL injection here:
        // I can't bind inputs to a query returned via $this->db->prepareQuery.
        return $this->db->execute('RELEASE SAVEPOINT ' . $this->retrieveLastSavepointName());
    }


    /**
     * Rollback all changes since the last savepoint was created.
     *
     * This removes the last savepoint from the stack that `createSavepoint`
     * added.
     *
     * Required because nested transactions don't exist in MySQL.
     *
     * @return bool
     * @throws OutOfRangeException if there's no savepoints in the stack
     */
    protected function rollbackSavepoint(): bool
    {
        // You'll just have to trust there's no SQL injection here:
        // I can't bind inputs to a query returned via $this->db->prepareQuery.
        return $this->db->execute('ROLLBACK TO SAVEPOINT ' . $this->retrieveLastSavepointName());
    }


    /**
     * Output a summary of what this script did.
     */
    protected function outputSummary(): void
    {
        // A cap/ind/work might have been reported as both fixed and failed,
        // if one part of the fix worked but another didn't.
        $fixedCapabilities = array_diff_key($this->fixedCapabilities, $this->failedCapabilities);
        $fixedIndicators   = array_diff_key($this->fixedIndicators, $this->failedIndicators);
        $fixedDueWorks     = array_diff_key($this->fixedDueWorks, $this->failedDueWorks);
        $this->log->notice('This script repaired:');
        $this->log->notice(self::indent(1) . sprintf('%d capabilities', count($fixedCapabilities)));
        $this->log->notice(self::indent(1) . sprintf('%d indicators', count($fixedIndicators)));
        $this->log->notice(self::indent(1) . sprintf('%d due works', count($fixedDueWorks)));


        // Collate a list of failures
        $failures              = [];
        $failedCapabilityCount = count($this->failedCapabilities);
        $failedIndicatorCount  = count($this->failedIndicators);
        $failedDueWorkCount    = count($this->failedDueWorks);
        $folderBaseUrl         = 'homepage/%d';
        $dueWorkBaseUrl        = 'learning/assessments/%d/modify';


        // Output which due works were fixed
        foreach ($fixedDueWorks as $id => $data) {
            $this->log->notice(
                self::indent(2) . sprintf(
                    '%s due work %s (%s)',
                    'https://' . $this->fqdn . '/' . sprintf($folderBaseUrl, $data['folderId']),
                    'https://' . $this->fqdn . '/' . sprintf($dueWorkBaseUrl, $id),
                    self::truncate($data['name'], self::MAX_LOG_ITEM_WIDTH)
                )
            );
        }


        // Report on what failed
        if (($failedCapabilityCount + $failedIndicatorCount + $failedDueWorkCount) > 0) {
            $this->log->warning('Manual intervention is required to repair:');

            if ($failedCapabilityCount > 0) {
                $this->log->warning(self::indent(1) . sprintf('%d capabilities:', $failedCapabilityCount));
                foreach ($this->failedCapabilities as $id => $data) {
                    $truncatedName = self::truncate($data['name'], self::MAX_LOG_ITEM_WIDTH);
                    $failures[]    = [
                        'url'   => sprintf($folderBaseUrl, $data['folderId']),
                        'title' => sprintf('capability #%d (%s)', $id, $truncatedName),
                    ];
                    $failureMsg = sprintf(
                        '%s capability #%d (%s)',
                        'https://' . $this->fqdn . '/' . sprintf($folderBaseUrl, $data['folderId']),
                        $id,
                        $truncatedName
                    );
                    $this->log->warning(self::indent(2) . $failureMsg);
                }
            }

            if ($failedIndicatorCount > 0) {
                $this->log->warning(self::indent(1) . sprintf('%d indicators:', $failedIndicatorCount));
                foreach ($this->failedIndicators as $id => $data) {
                    $truncatedName = self::truncate($data['name'], self::MAX_LOG_ITEM_WIDTH);
                    $failures[]    = [
                        'url'   => sprintf($folderBaseUrl, $data['folderId']),
                        'title' => sprintf('indicator #%d (%s)', $id, $truncatedName),
                    ];
                    $failureMsg = sprintf(
                        '%s indicator #%d (%s)',
                        'https://' . $this->fqdn . '/' . sprintf($folderBaseUrl, $data['folderId']),
                        $id,
                        $truncatedName
                    );
                    $this->log->warning(self::indent(2) . $failureMsg);
                }
            }

            if ($failedDueWorkCount > 0) {
                $this->log->warning(self::indent(1) . sprintf('%d due works:', $failedDueWorkCount));
                foreach ($this->failedDueWorks as $id => $data) {
                    $truncatedName = self::truncate($data['name'], self::MAX_LOG_ITEM_WIDTH);
                    $failures[]    = [
                        'url'   => sprintf($dueWorkBaseUrl, $id),
                        'title' => sprintf('due work #%d (%s)', $id, $truncatedName),
                    ];
                    $failureMsg = sprintf(
                        '%s due work %s (%s)',
                        'https://' . $this->fqdn . '/' . sprintf($folderBaseUrl, $data['folderId']),
                        'https://' . $this->fqdn . '/' . sprintf($dueWorkBaseUrl, $id),
                        $truncatedName
                    );
                    $this->log->warning(self::indent(2) . $failureMsg);
                }
            }

            $subject = sprintf(
                '%s (%s) has data which requires manual intervention',
                $this->getConfig()->get('title'),
                $this->getConfig()->get('site_school_code')
            );
            $this->sendMail(
                'scriptOutput.twig',
                $subject,
                ['output' => $failures,]
            );
            $this->log->alert('Merging incomplete, could not merge non-destructively.');
        }
    }
}
