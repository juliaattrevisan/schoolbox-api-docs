<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair;

use Alaress\Database\Connector\IConnector;
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
