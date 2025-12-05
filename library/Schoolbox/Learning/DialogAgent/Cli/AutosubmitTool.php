<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent\Cli;

use Schoolbox\Application\CliTool;

/**
 * Dialog Agent Autosubmit
 *
 * Automatically submits any unsubmitted responses made by students when the activity's Due/Close Date
 * is reached. It will submit on behalf of the student.
 */
class AutosubmitTool extends CliTool\App
{
    // --help: print help
    private const string OPT_LONG_HELP = 'help';


    /**
     * For activities that have expired (reached their due/close datetime)
     */
    private const string OPT_LONG_EXPIRED = 'expired';


    /**
     * For responses that have exceeded their allowed duration
     */
    private const string OPT_LONG_DURATION = 'duration';


    /**
     * A prepared statement that we'll reuse throughout this tool
     */
    private const string QUERY_UPDATE = "
        UPDATE dialog_agent_response
        SET 
            submitted_at = :submittedAt,
            submitted_by = :submittedBy
        WHERE id = :id
        LIMIT 1
    ";


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_EXPIRED,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Activity Expiry
---------------
Dialog Agents (Discussion Activities) allow students to interact with an AI to discuss topics. Students can start a
discussion but neglect to officially submit their response to the activity. This tool will set the submitted_at value
to the end datetime of the activity on behalf of the student when the activity reaches its due/close date (these dates
are synonymous specifically in Dialog Agents). The student will be presumed to be the person who submitted it since we
don't have a "system user" to do that.
 
Usage:    autosubmit.php --expired --perform [-vvv]


Response Duration
-----------------
A secondary usage is when students' responses took longer than the time that was allowed per response. That can occur 
when students start their response, but then shut their computers and walk away until the allotted time has elapsed. 
That could be by choice (non-participation) or by circumstance (eg. the internet turned off). Such responses, if they
remained in an unsubmitted state, will be arbitrarily submitted on behalf of the student.

Usage:    autosubmit.php --duration --perform [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Dialog Agent Autosubmit');
        $this->getLogger()->info('');

        if ($this->getOpt(self::OPT_LONG_EXPIRED)) {
            $this->autosubmitExpirededActivities();
        }

        if ($this->getOpt(self::OPT_LONG_DURATION)) {
            $this->autosubmitDurationExceeded();
        }

        $this->getLogger()->info('');

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
            $this->getLogger()->info('');
        }
    }


    /**
     * Autosubmits responses associated with activities that reached due/close datetime.
     *
     * @return void
     */
    private function autosubmitExpirededActivities(): void
    {
        $statement = $this->getDatabaseInternal()->prepare("
            SELECT 
                dar.id, dar.learner_id,
                a.title, a.due_date,
                da.assessment_id
            FROM assessment a
            INNER JOIN dialog_agent da ON da.assessment_id = a.id
            INNER JOIN dialog_agent_response dar ON dar.dialog_agent_id = da.id
            INNER JOIN user u ON dar.learner_id = u.id
            WHERE (a.close_date < NOW() OR a.due_date < NOW())
            AND dar.submitted_at IS NULL
        ");

        $statement->execute();

        $updateStatement = null;

        $n = 0;

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $n++;

            /* Prepare a reusable query only if it was never set up previously
             */
            if (null === $updateStatement && $this->perform()) {
                $updateStatement = $this->getDatabaseInternal()->prepare(self::QUERY_UPDATE);
            }

            if ($this->perform()) {
                $updateStatement->execute([
                    'id' => $row['id'],
                    'submittedAt' => $row['due_date'],
                    'submittedBy' => $row['learner_id'],
                ]);
            }

            $this->getLogger()->info(sprintf(
                'Submitting response id %s for user %s in discussion activity "%s" (%s) - activity expired.',
                $row['id'],
                $row['learner_id'],
                $row['title'],
                $row['assessment_id']
            ));
        }

        if (!$n) {
            $this->getLogger()->info('No unsubmitted responses found for expired activities.');
        }
    }


    /**
     * Autosubmits responses that have exceeded their allotted durations
     *
     * @return void
     */
    private function autosubmitDurationExceeded(): void
    {
        /* Find all the unsubmitted responses to dialog agents that enforce a duration per response
         */
        $statement = $this->getDatabaseInternal()->prepare("
            SELECT 
                dar.id, dar.learner_id,
                a.title, a.due_date,
                da.assessment_id
            FROM assessment a
            INNER JOIN dialog_agent da ON da.assessment_id = a.id
            INNER JOIN dialog_agent_response dar ON dar.dialog_agent_id = da.id
            INNER JOIN user u ON dar.learner_id = u.id
            WHERE da.duration IS NOT NULL
            AND da.duration > 0
            AND dar.submitted_at IS NULL
        ");

        $statement->execute();

        $updateStatement = null;

        $n = 0;

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* TODO: Check if the duration exceeded the allotted amount
             *  If it did, process it
             *  Otherwise, skip it for now
             */

            $n++;

            /* Prepare a reusable query only if it was never set up previously
             */
            if (null === $updateStatement && $this->perform()) {
                $updateStatement = $this->getDatabaseInternal()->prepare(self::QUERY_UPDATE);
            }

            if ($this->perform()) {
                $updateStatement->execute([
                    'id' => $row['id'],
                    'submittedAt' => $row['due_date'],
                    'submittedBy' => $row['learner_id'],
                ]);
            }

            $this->getLogger()->info(sprintf(
                'Submitting response id %s for user %s in discussion activity "%s" (%s) - duration exceeded.',
                $row['id'],
                $row['learner_id'],
                $row['title'],
                $row['assessment_id']
            ));
        }

        if (!$n) {
            $this->getLogger()->info('No unsubmitted responses found for excessive durations.');
        }
    }
}
