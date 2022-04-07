#!/usr/bin/php
<?php

/**
 * This script takes a CSV file and uploads the contents into the pastoral system.  The CSV file must be in a specific
 * format and all records are created with standard visibility rules.
 *
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Pastoral;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_FILE   = 'file:';             // csv file


    /**
     * List of group IDs that will be able to see imported records
     */
    private $groupAccess = [3, //Counsellors
                            8, //Principal and Deputy
                            9, //Subschool Heads
        ];

    /**
     * @var Pastoral\Mapper
     */
    private $pastoralMapper;


    /**
     * @var Pastoral\Access\Mapper
     */
    private $pastoralAccessMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {


        if ($this->getOpt(self::OPT_LONG_HELP) || !(bool)$this->getOpt(self::OPT_LONG_FILE)) {
            echo <<<HELP
PBS Import pastoral records.

Usage: pastoral-import.php --file filename.csv [--perform]

HELP;
            exit;
        }


        /* Mappers
         */
        $this->pastoralMapper = Pastoral\Mapper::getInstance();
        $this->pastoralAccessMapper = Pastoral\Access\Mapper::getInstance();
        $this->userMapper = UserMapper::getInstance();


        // Import csv
        $file   = $this->getOpt(self::OPT_LONG_FILE);

        $reader = new Reader($file, true, true);
        if (!$reader->readHeaderRow()) {
            $this->getLogger()->warning("Unable to read header from the file: {$file}");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $reader->setExpectedColumns([
            'owner_id',
            'severity_id',
            'student_id',
            'type_id',
            'subtype_id',
            'heading',
            'body_private',
            'body_public',
            'points',
        ]);
        $reader->next(); // header

        $currentFeedbackRows = [];

        $reader->next();
        $count = 1;
        $this->userMapper->getDatabase()->beginTransaction();
        while ($reader->valid()) {
            try {
                $this->process($reader->current());
            } catch (Throwable $e) {
                $this->getLogger()->warning(
                    sprintf('Could not import row %d: %s', $reader->key(), $e->getMessage())
                );
            }
            $reader->next();
            $count++;
        }

        if ($this->perform()) {
            $this->getLogger()->info("Processed {$count} records.");
            $this->userMapper->getDatabase()->commit();
        } else {
            $this->getLogger()->info("{$count} feedback will be processed.");
            $this->userMapper->getDatabase()->rollback();
        }
    }

    function process(array $row)
    {

        $pastoral = new Pastoral\Model();
        try {
            $pastoral->setOwner($this->userMapper->findById((int)$row['owner_id']));
        } catch (Mapper\Exception $e) {
            throw new InvalidArgumentException(sprintf('Could not find owner #%d', $row['owner_id']));
        }
        //$pastoral->setSeverity((new Pastoral\Severity\Model())->setId((int)$row['severity_id']));
        $pastoral->setSeverity(null);
        try {
            $pastoral->setStudent($this->userMapper->findById((int)$row['student_id']));
        } catch (Mapper\Exception $e) {
            throw new InvalidArgumentException(sprintf('Could not find student #%d', $row['student_id']));
        }
        $pastoral->setType((new Pastoral\Type\Model())->setId((int)$row['type_id']));
        $pastoral->setSubType((new Pastoral\SubType\Model())->setId((int)$row['subtype_id']));
        $pastoral->setHeading(trim($row['heading']));
        $pastoral->setBodyPrivate('<p>' . trim($row['body_private']) . '</p>');
        $pastoral->setBodyPublic('<p>' . trim($row['body_public']) . '</p>');
        $pastoral->setPoints((float)$row['points']);
        $pastoral->setModifiedBy((new User())->setId((int)$row['owner_id']));

        $this->pastoralMapper->save($pastoral);

        foreach ($this->groupAccess as $group) {
            $pastoralAccess = new Pastoral\Access\Model();
            $pastoralAccess->setGroup((new Schoolbox\Pastoral\Group\Model())->setId($group));
            $pastoralAccess->setAccess(Pastoral\Access\Mapper::ACCESS_PRIVATE);
            $pastoralAccess->setPastoral($pastoral);
            $this->pastoralAccessMapper->save($pastoralAccess);
        }
    }

    /**
     * Must run as root for file access
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return false;
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
