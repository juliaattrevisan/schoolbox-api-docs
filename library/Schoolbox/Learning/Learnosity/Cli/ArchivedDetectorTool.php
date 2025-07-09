<?php

namespace Schoolbox\Learning\Learnosity\Cli;

use Alaress\Database\Connector\IConnector;
use JsonException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api as LearnosityApi;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Url\Factory;

class ArchivedDetectorTool extends CliTool\App
{
    /**
     * Learnosity has a 1000-thing limit per array.
     * Because it does.
     */
    private const PAYLOAD_ITEM_LIMIT = 999;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @var IConnector
     */
    private IConnector $db;


    /**
     * @var LearnosityApi
     */
    private LearnosityApi $api;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Learnosity Activity Status

In Planning mode, this will tell you the status of all the quizzes that have learnosity references.
This is used to interrogate learnosity for quizzes that should have been in Published state, but are 
currently in Archived, due to a bug where you were able to export archived quizzes to classes. 

That bug's been repaired, but there might be lingering quizzes that haven't been manually restored: 
such quizzes are dead on arrival for users and it's too difficult to make a list of them all.


--perform
This makes a request to Learnosity to change the status of a quiz in their system. If combined with
verbose mode -vvv you'll get a breakdown of all the items it changed as well. This occurs via passthru
to the Restore tool 


Usage: archive-mass-restore.php [--course]  [--perform] -vvv


HELP;
            return;
        }

        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Activity Status and Mass Restoration');
        $this->getLogger()->info('');

        /* Build dependencies
         */
        $this->db = $this->getDi()->get('databaseInternal');

        $this->api = new LearnosityApi(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        /* Start detecting all the living schoolbox quizzes
         */
        $manifest = $this->findManifest();
        $n = count($manifest);

        $this->getLogger()->info('');
        $this->getLogger()->info('');

        /* No quizzes? No trabajo
         */
        if (!$n) {
            $this->getLogger()->info('');
            return;
        }

        $currentStatusLookup = $this->findCurrentStatus(array_column($manifest, 'learnosity_reference'));

        /* Learnosity died
         */
        if (!$currentStatusLookup) {
            $this->getLogger()->warning('Learnosity died trying to fetch current statuses; their service might be down.');

            return;
        }

        print_r($currentStatusLookup);

        /* For access to the restore() mechanism
         */
        $helper = (new Helper($this->api, $this->getLogger()));


        //
//        /* Start inspecting each quiz to find out what it's doing
//         */
//        $i = 0;
//
//        foreach ($manifest as $data) {
//            $quizStatus = $currentStatusLookup[$data['learnosity_reference']];
//
//            $this->getLogger()->info(sprintf(
//                '[%s/%s] "%s" in homepage "%s" /homepage/%s',
//                ++$i,
//                $n,
//                $data['assessment_title'],
//                $data['folder_name'],
//                $data['folder_id']
//            ));
//
//            $this->getLogger()->info(sprintf(
//                '    Assessment url: /learning/assessments/%s',
//                $data['assessment_id']
//            ));
//
//            $this->getLogger()->info(sprintf(
//                '    Learnosity reference: %s',
//                $data['learnosity_reference'],
//            ));
//
//            $this->getLogger()->info(sprintf(
//                '    Current status: %s',
//                $quizStatus
//            ));
//
//            /* Perform mode actually does the restoration, but only for Archived quizzes.
//             * Quizzes in any other state will be left untouched
//             */
//            if ($this->perform()) {
//                if (Helper::LEARNOSITY_ITEM_STATUS_ARCHIVED === $quizStatus) {
//                    $this->getLogger()->info('        Restoring via learnosity...');
//
//                    $helper->restore($data['learnosity_reference']);
//
//                    /* Log status of the ones we just changed, requesting their current state
//                     * from learnosity as a confirmation
//                     */
//                    try {
//                        $confirmation = $this->api->dataGetRequest('/itembank/activities', [
//                            'references' => [
//                                $data['learnosity_reference']
//                            ]
//                        ]);
//                    } catch (JsonException | ValidationException $e) {
//                        $this->getLogger()->error('Request to /itembank/activities failed.  ' . $e->getMessage());
//
//                        return;
//                    }
//
//                    $this->getLogger()->info(sprintf(
//                        '        Updated status: %s',
//                        $confirmation['data'][0]['status'] ?? 'unknown'
//                    ));
//                }
//            }
//
//            $this->getLogger()->info('');
//        }

        /* Announce conclusion
         */
        $this->getLogger()->info('');

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');
    }


    /**
     * Scouts the entire db for all the implementations of its spec assessments;
     * if they're a quiz, figure out if it's in learnosity. If it is, interrogate
     * its status (archived/published)
     *
     * If it's archived, it's probably meant to be published instead: restore it
     * the same way we did in the Restore tool
     *
     * @return array
     */
    private function findManifest(): array
    {
        $query = "
            SELECT f1.id AS folder_id, f1.name AS folder_name,
                   a.id AS assessment_id, a.title AS assessment_title, a.deleted_at,
                   q.learnosity_reference
            FROM mv_assessment_relation mvar
            INNER JOIN folder f1 ON mvar.folder_id = f1.id
            INNER JOIN assessment a ON mvar.assessment_id = a.id
            INNER JOIN assess q ON a.id = q.assessment_id
            WHERE q.deleted_at IS NULL
            AND q.learnosity_reference IS NOT NULL
            AND q.learnosity_reference != ''
            ORDER BY folder_name
        ";

        $statement = $this->db->prepare($query);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }


    /**
     * Find out whether
     *
     * @param array $learnosityReferences
     * @return array|null
     */
    private function findCurrentStatus(array $learnosityReferences): ?array
    {
        $result = null;
        $stack = [];

        $chunks = array_chunk($learnosityReferences, self::PAYLOAD_ITEM_LIMIT);

        foreach ($chunks as $chunk) {
            try {
                $result = $this->api->dataGetRequest('/itembank/activities', ['references' => $chunk]);
            } catch (JsonException | ValidationException $e) {
                $this->getLogger()->error('Request to /itembank/activities failed.  ' . $e->getMessage());
            }

            /* Build a hash lookup of (learnosity reference, status) tuples for learnosity quizzes
             * were archived, but the schoolbox quiz still alive. There shouldn't be --that-- many in
             * such a broken state that collecting their statuses here would pose a resourcing problem.
             */
            foreach ($result['data'] as $activity) {
                if (Helper::LEARNOSITY_ITEM_STATUS_ARCHIVED === $activity['status']) {
                    $this->getLogger()->debug(sprintf(
                        'Quiz with learnosity reference %s was archived in learnosity: adding to stack',
                        $activity['reference']
                    ));

                    $stack[$activity['reference']] = $activity['status'];
                } else {
                    $this->getLogger()->debug(sprintf(
                        'Quiz with learnosity reference %s is already published in learnosity: skipping',
                        $activity['reference']
                    ));
                }
            }
        }

        return $stack;
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
