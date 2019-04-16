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
use RuntimeException;
use Schoolbox\Application\CliTool;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Scrub;
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

        $this->outputIntro();

        if ($this->perform()) {
            $this->log->info('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->info('Running in dry-run mode: use -p to write changes to database');
        }

        $this->log->notice('-----');
        $this->repair();
        $this->log->notice('-----');
        $this->outputSummary();
        $this->log->notice('-----');

        if ($this->perform()) {
            $this->log->alert('Changes written to database');
        } else {
            $this->log->alert('Dry-run complete - no changes written to database');
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
            $mailer = new TwigEmail($template);
            $mailer->setCoreContext((new CoreContext($this->getConfig()))->getContext());
            $mailer->setFrom($this->getConfig()->get('mail_from_address'));
            $mailer->setSubject($subject);
            $mailer->setContext($context);
            try {
                $mailer->send($mailto);
            } catch (Swift_SwiftException $e) {
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
     * Prepare any queries this script may need.
     */
    protected abstract function setupQueries(): void;


    /**
     * Actually run the repair. Run after queries are set up.
     */
    protected abstract function repair(): void;


    /**
     * Output a summary of what this script will do.
     */
    protected abstract function outputIntro(): void;


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
        $failures = [];
        foreach ($this->failedCapabilities as $id => $data) {
            $failures[$data['folderId']]['capability:' . $id] = [
                'name' => $data['name'],
                'type' => 'capability',
            ];
        }
        foreach ($this->failedIndicators as $id => $data) {
            $failures[$data['folderId']]['indicator:' . $id] = [
                'name' => $data['name'],
                'type' => 'indicator',
            ];
        }
        foreach ($this->failedDueWorks as $id => $data) {
            $failures[$data['folderId']]['duework:' . $id] = [
                'name' => $data['name'],
                'type' => 'duework',
            ];
        }


        // Report on what failed
        $failedCapabilityCount = count($this->failedCapabilities);
        $failedIndicatorCount  = count($this->failedIndicators);
        $failedDueWorkCount    = count($this->failedDueWorks);
        $folderBaseUrl         = 'homepage/%d';
        $dueWorkBaseUrl        = 'learning/assessments/%d/modify';
        $failures              = [];

        if (($failedCapabilityCount + $failedIndicatorCount + $failedDueWorkCount) > 0) {
            $this->log->warning('Manual intervention is required to repair:');

            if ($failedCapabilityCount > 0) {
                $this->log->warning(self::indent(1) . sprintf('%d capabilities:', $failedCapabilityCount));
                foreach ($this->failedCapabilities as $id => $data) {
                    $failures[] = [
                        'url'   => sprintf($folderBaseUrl, $data['folderId']),
                        'title' => sprintf('capability #%d (%s)', $id, $data['name']),
                    ];
                    $failureMsg = sprintf(
                        '%s capability #%d (%s)',
                        'https://' . $this->fqdn . sprintf($folderBaseUrl, $data['folderId']),
                        $id,
                        $data['name']
                    );
                    $this->log->warning(self::indent(2) . $failureMsg);
                }
            }

            if ($failedIndicatorCount > 0) {
                $this->log->warning(self::indent(1) . sprintf('%d indicators:', $failedIndicatorCount));
                foreach ($this->failedIndicators as $id => $data) {
                    $failures[] = [
                        'url'   => sprintf($folderBaseUrl, $data['folderId']),
                        'title' => sprintf('indicator #%d (%s)', $id, $data['name']),
                    ];
                    $failureMsg = sprintf(
                        '%s indicator #%d (%s)',
                        'https://' . $this->fqdn . sprintf($folderBaseUrl, $data['folderId']),
                        $id,
                        $data['name']
                    );
                    $this->log->warning(self::indent(2) . $failureMsg);
                }
            }

            if ($failedDueWorkCount > 0) {
                $this->log->warning(self::indent(1) . sprintf('%d due works:', $failedDueWorkCount));
                foreach ($this->failedDueWorks as $id => $data) {
                    $failures[] = [
                        'url'   => sprintf($dueWorkBaseUrl, $id),
                        'title' => sprintf('due work #%d (%s)', $id, $data['name']),
                    ];
                    $failureMsg = sprintf(
                        '%s due work %s (%s)',
                        'https://' . $this->fqdn . sprintf($folderBaseUrl, $data['folderId']),
                        'https://' . $this->fqdn . sprintf($dueWorkBaseUrl, $id),
                        $data['name']
                    );
                    $this->log->warning(self::indent(2) . $failureMsg);
                }
            }

            $subject = sprintf(
                '%s (%s) has data which requires manual intervention',
                $this->getConfig()->get('title'),
                $this->getConfig()->get('site_location')
            );
            $this->sendMail(
                'scriptOutput.twig',
                $subject,
                ['output' => $failures,]
            );
            throw new RuntimeException('Merging incomplete, could not merge non-destructively.');
        }
    }
}
