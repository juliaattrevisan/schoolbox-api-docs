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
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Dialog Agents (Discussion Activities) allow students to interact with an AI to discuss topics. Students can start a
discussion but neglect to officially submit their response to the activity. This tool will set the submitted_at value
to the end datetime of the activity on behalf of the student when the activity reaches its due/close date (these dates
are synonymous specifically in Dialog Agents). The student will be presumed to be the person who submitted it since we
don't have a "system user" to do that.
 
Usage:    autosubmit.php --perform [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Dialog Agent Autosubmit');
        $this->getLogger()->info('');

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
                'Submitting response id %s for user %s in discussion activity "%s" (%s)',
                $row['id'],
                $row['learner_id'],
                $row['title'],
                $row['assessment_id']
            ));
        }

        if (!$n) {
            $this->getLogger()->info('No unsubmitted responses found.');
        }

        $this->getLogger()->info('');

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
            $this->getLogger()->info('');
        }
    }
}
