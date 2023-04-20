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
            echo "\nUsage: php " . __FILE__ . "\n\n";
            exit;
        }

        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        try {
            $rows = $dbc->executeQuery("SELECT value FROM config WHERE name='site_school_code'")->fetchAll();
            $schoolCode = $rows[0]['value'];
        } catch (Exception $e) {
            $logger->info("Could not find school code.  Stopping.\n");
            die;
        }

        $logger->info("Found school code '$schoolCode'.\n");

        $valueByKeyByTerm = [];

        switch (strtolower($schoolCode)) {
            case 'cccl':
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'NESA number',
                    'plural_form' => 'NESA numbers',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'dsfg':
                $valueByKeyByTerm['headOfHouse'] = [
                    'singular_form' => 'Staff House Leader',
                    'plural_form' => 'Staff House Leaders',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'ec':
                $valueByKeyByTerm['tutor'] = [
                    'singular_form' => 'Mentor',
                    'plural_form' => 'Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'fct':
                $valueByKeyByTerm['tutor'] = [
                    'singular_form' => 'Holme Group Teacher',
                    'plural_form' => 'Holme Group Teachers',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'Holme Group',
                    'plural_form' => 'Holme Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'kgsl':
                $valueByKeyByTerm['yearLevelCoordinator'] = [
                    'singular_form' => 'Head of Year',
                    'plural_form' => 'Heads of Years',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'Dean of Wellbeing',
                    'plural_form' => 'Deans of Wellbeing',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'lk':
                $valueByKeyByTerm['tutor'] = [
                    'singular_form' => 'Academic Care Coordinator',
                    'plural_form' => 'Academic Care Coordinators',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'an'
                ];
                $valueByKeyByTerm['yearLevelCoordinator'] = [
                    'singular_form' => 'Year Wellbeing Coordinator',
                    'plural_form' => 'Year Wellbeing Coordinators',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['headOfHouse'] = [
                    'singular_form' => 'House Academic Care Coordinator',
                    'plural_form' => 'House Academic Care Coordinators',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'Circle Class',
                    'plural_form' => 'Circle Classes',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'newic':
                $valueByKeyByTerm['tutor'] = [
                    'singular_form' => 'Mentor',
                    'plural_form' => 'Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'Mentor Group',
                    'plural_form' => 'Mentor Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'scg':
                $valueByKeyByTerm['tutor'] = [
                    'singular_form' => 'Learning Mentor',
                    'plural_form' => 'Learning Mentors',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'shalcc':
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'PC Group',
                    'plural_form' => 'PC Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            case 'wsl':
                $valueByKeyByTerm['tutor'] = [
                    'singular_form' => 'Connect Teacher',
                    'plural_form' => 'Connect Teachers',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                $valueByKeyByTerm['tutorGroup'] = [
                    'singular_form' => 'Connect Group',
                    'plural_form' => 'Connect Groups',
                    'capitalise_first' => 1,
                    'indefinite_article' => 'a'
                ];
                break;

            default:
                $logger->info("No localisation terms require update.\n");
        }

        foreach ($valueByKeyByTerm as $term => $valueByKey) {
            $statement = $dbc->executeQuery(
                "UPDATE localisation SET
                singular_form=:singularForm,
                plural_form=:pluralForm,
                capitalise_first=:capitaliseFirst,
                indefinite_article=:indefiniteArticle
                WHERE term=:term",
                [
                    'singularForm' => $valueByKey['singular_form'],
                    'pluralForm' => $valueByKey['plural_form'],
                    'capitaliseFirst' => $valueByKey['capitalise_first'],
                    'indefiniteArticle' => $valueByKey['indefinite_article'],
                    'term' => $term
                ]
            );

            $nRowsAffected = $statement->rowCount();
            $termValue = $valueByKey['singular_form'];

            switch ($nRowsAffected) {
                case 0:
                    $logger->info("No rows affected when trying to update '$term' to '$termValue'.  Probably already updated.\n");
                    break;
                case 1:
                    $logger->info("Updated '$term' to '$termValue'.");
                    break;
                default:
                    $logger->info("Updated '$term' to '$termValue', but multiple rows affected.  Not good.\n");
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
