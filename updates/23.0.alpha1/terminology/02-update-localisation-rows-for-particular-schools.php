<?php

# SQL commands targeting particular schools.  These will have to be modified somehow to make them work as intended.

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
This script will update the following localisation terms for particular schools:
 * tutor
 * tutorGroup
 * yearLevelCoordinator
 * headOfHouse
HELP;
            echo "\n\nUsage: php " . __FILE__ . "\n\n";
            exit;
        }

        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();
        $scriptName = 'Localisation updater';

        try {
            $rows = $dbc->executeQuery("SELECT value FROM config WHERE name='site_school_code'")->fetchAll();
            $schoolCode = $rows[0]['value'];
        } catch (Exception $e) {
            $logger->info(sprintf('%s: Could not find school code.  Stopping.', $scriptName));
            die;
        }

        $logger->info(sprintf("%s: Found school code '%s'.", $scriptName, $schoolCode));

        $valueByKeyByTerm = [];

        switch (strtolower($schoolCode)) {
            case 'cccl':
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'NESA Number',
                    'singular_form' => 'NESA number',
                    'plural_form' => 'NESA numbers',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'dsfg':
                $valueByKeyByTerm['headOfHouse'] = [
                    'title' => 'Staff House Leader',
                    'singular_form' => 'Staff House Leader',
                    'plural_form' => 'Staff House Leaders',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'ec':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Mentor',
                    'singular_form' => 'Mentor',
                    'plural_form' => 'Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'fct':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Holme Group Teacher',
                    'singular_form' => 'Holme Group Teacher',
                    'plural_form' => 'Holme Group Teachers',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'Holme Group',
                    'singular_form' => 'Holme Group',
                    'plural_form' => 'Holme Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'kgsl':
                $valueByKeyByTerm['yearLevelCoordinator'] = [
                    'title' => 'Head of Year',
                    'singular_form' => 'Head of Year',
                    'plural_form' => 'Heads of Years',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'Dean of Wellbeing',
                    'singular_form' => 'Dean of Wellbeing',
                    'plural_form' => 'Deans of Wellbeing',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'lk':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Academic Care Coordinator',
                    'singular_form' => 'Academic Care Coordinator',
                    'plural_form' => 'Academic Care Coordinators',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'an'
                ];
                $valueByKeyByTerm['yearLevelCoordinator'] = [
                    'title' => 'Year Wellbeing Coordinator',
                    'singular_form' => 'Year Wellbeing Coordinator',
                    'plural_form' => 'Year Wellbeing Coordinators',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['headOfHouse'] = [
                    'title' => 'House Academic Care Coordinator',
                    'singular_form' => 'House Academic Care Coordinator',
                    'plural_form' => 'House Academic Care Coordinators',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'Circle Class',
                    'singular_form' => 'Circle Class',
                    'plural_form' => 'Circle Classes',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'newic':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Mentor',
                    'singular_form' => 'Mentor',
                    'plural_form' => 'Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'Mentor Group',
                    'singular_form' => 'Mentor Group',
                    'plural_form' => 'Mentor Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'scg':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Learning Mentor',
                    'singular_form' => 'Learning Mentor',
                    'plural_form' => 'Learning Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'shallcc':
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'PC Group',
                    'singular_form' => 'PC Group',
                    'plural_form' => 'PC Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'wsl':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Connect Teacher',
                    'singular_form' => 'Connect Teacher',
                    'plural_form' => 'Connect Teachers',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'Connect Group',
                    'singular_form' => 'Connect Group',
                    'plural_form' => 'Connect Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'vagrant':
                $valueByKeyByTerm['tutor'] = [
                    'title' => 'Mentor',
                    'singular_form' => 'Mentor',
                    'plural_form' => 'Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'title' => 'Tutor-Group-TEST',
                    'singular_form' => 'Tutor-Group-TEST',
                    'plural_form' => 'Tutor-Group-TESTs',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['yearLevelCoordinator'] = [
                    'title' => 'Year-Level-Coordinator-TEST',
                    'singular_form' => 'Year-Level-Coordinator-TEST',
                    'plural_form' => 'Year-Level-Coordinator-TESTs',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['headOfHouse'] = [
                    'title' => 'Head-of-House-TEST',
                    'singular_form' => 'Head-of-House-TEST',
                    'plural_form' => 'Head-of-House-TESTs',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            default:
                $logger->info(sprintf("%s: No localisation terms require update.", $scriptName));
        }

        foreach ($valueByKeyByTerm as $term => $valueByKey) {
            $statement = $dbc->executeQuery(
                "UPDATE localisation SET
                title=:title,
                singular_form=:singularForm,
                plural_form=:pluralForm,
                capitalise_first=:capitaliseFirst,
                indefinite_article=:indefiniteArticle
                WHERE term=:term",
                [
                    'title' => $valueByKey['title'],
                    'singularForm' => $valueByKey['singular_form'],
                    'pluralForm' => $valueByKey['plural_form'],
                    'capitaliseFirst' => $valueByKey['capitalise_first'],
                    'indefiniteArticle' => $valueByKey['indefinite_article'],
                    'term' => $term
                ]
            );

            $nRowsAffected = $statement->rowCount();

            switch ($nRowsAffected) {
                case 0:
                    $logger->info(sprintf(
                        "%s: No rows affected when trying to update '%s' to '%s'.  Probably already updated.",
                        $scriptName, $term, $valueByKey['title']
                    ));
                    break;
                case 1:
                    $logger->info(sprintf("%s: Updated '%s' to '%s'.", $scriptName, $term, $valueByKey['title']));
                    break;
                default:
                    $logger->info(sprintf(
                        "%s: Updated '%s' to '%s', but multiple rows affected.  Not good.",
                        $scriptName, $term, $valueByKey['title']
                    ));
                    break;
            }
        }

        echo "\n";
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
