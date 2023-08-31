<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App
{
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


HELP;
            exit;
        }

        $db = $this->getDatabaseInternal();

        // grab all summative reports to update
        $reports = $db->executeQuery('SELECT id, _options FROM summative_report')->fetchAll();

        $this->getLogger()->info(sprintf("Updating %s summative reports", count($reports)));
        foreach ($reports as $row) {
            $new = $pre = json_decode($row['_options'], true, 512, JSON_THROW_ON_ERROR);

            $this->getLogger()->debug(sprintf("Report: #%s", $row['id']));

            $new['summary']['workType'] = [];
            $new['class']['workType'] = [];

            foreach ($pre['summary']['workType'] as $id) {
                // spawn new worktype
                $new['summary']['workType'][$id] = [
                    'grades' => $pre['summary']['grades'],
                    'comments' => $pre['summary']['comments'],
                    'rubrics' => $pre['summary']['rubrics'],
                ];
            }
            foreach ($pre['class']['workType'] as $id) {
                // spawn new worktype
                $new['class']['workType'][$id] = [
                    'grades' => $pre['class']['grades'],
                    'comments' => $pre['class']['comments'],
                    'rubrics' => $pre['class']['rubrics'],
                ];
            }

            // remove old grades / comments / rubrics settings
            unset($new['class']['grades'], $new['class']['comments'], $new['class']['rubrics'], $new['summary']['grades'], $new['summary']['comments'], $new['summary']['rubrics']);

            $query = (
            "UPDATE summative_report
                 SET options=:newOptions
                 WHERE id=:id"
            );
            $statement = $db->prepareQuery($query, ['id' => $row['id'], 'newOptions' => json_encode($new)]);
            $statement->execute();
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

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
