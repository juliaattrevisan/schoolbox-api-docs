#!/usr/bin/php
<?php

/**
 * This script takes a CSV file and uploads the contents into the pastoral system.  The CSV file must be in a specific
 * format and all records are created with standard visibility rules.
 *
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Pastoral;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App {
    // --help: print help
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_FILE = 'file:';           // csv file
    private const OPT_LONG_DIR  = 'dir:';            // directory for attachments

    private const DEFAULT_PASTORAL_TYPE     = 'Other';
    private const DEFAULT_PASTORAL_SUBTYPE  = 'Other';
    private const DEFAULT_PASTORAL_SEVERITY = 'N/A';

    private const PASTORAL_TYPE_MAP = [
        'APPEARANCE'     => 'Uniform/Appearance',
        'TRUANCY'        => 'Lateness/Truancy',
        'ABSENCE'        => 'Absence/Leave',
        'BEHAVIOUR'      => 'Behaviour',
        'STUAFFECTED'    => 'Student affected',
        'LEARNINGCONC'   => 'Learning concern',
        'PROGRESSUPDATE' => 'Progress update',
        'MEDICAL'        => 'Medical',
        'EMOTIONAL'      => 'Social/emotional',
        'PARENTAL'       => 'Parent matter',
        'OTHER'          => 'Other',
    ];

    private const PASTORAL_SUBTYPE_MAP = [
        'COMMSERVICE'   => 'Community service',
        'DETENTION'     => 'Other',
        'DISCUSSION'    => 'Other',
        'N/A'           => 'Other',
        'OTHER'         => 'Other',
        'PARENTMEETING' => 'Parent meeting',
        'RESUBMISSION'  => 'Resubmission',
        'SUSPENSION'    => 'Other',
        'WITHDRAWAL'    => 'Withdrawal',
    ];

    /** @var Pastoral\Mapper */
    private $pastoralMapper;


    /**  @var Pastoral\Access\Mapper */
    private $pastoralAccessMapper;


    /** @var UserMapper */
    private $userMapper;


    /** @var \Schoolbox\Resource\Storage\Mapper */
    private $storageMapper;

    /** @var Pastoral\Attachment\Mapper; */
    private $pastoralAttachmentMapper;

    /** @var string $dirName */
    private $dirName;

    /** @var Collection */
    private $pastoralTypes;


    /** @var Collection */
    private $pastoralSeverities;


    /** @var Collection */
    private $roles;

    /** @var Collection */
    private $pastoralGroups;

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE,
            self::OPT_LONG_DIR,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || !(bool)$this->getOpt(self::OPT_LONG_FILE) || !(bool)$this->getOpt(self::OPT_LONG_DIR)) {
            echo <<<HELP
WGS Import pastoral records.

Usage: pastoral-import.php --file filename.csv --dir directory [--perform]

HELP;
            exit;
        }


        /* Mappers
         */
        $this->pastoralMapper           = Pastoral\Mapper::getInstance();
        $this->pastoralAccessMapper     = Pastoral\Access\Mapper::getInstance();
        $this->userMapper               = UserMapper::getInstance();
        $this->storageMapper            = \Schoolbox\Resource\Storage\Mapper::getInstance();
        $this->pastoralAttachmentMapper = Pastoral\Attachment\Mapper::getInstance();

        // Prepare lookups
        $pastoralTypeMapper  = Pastoral\Type\Mapper::getInstance();
        $this->pastoralTypes = $pastoralTypeMapper->findAll()->indexBy('name');

        $pastoralSeverityMapper   = Pastoral\Severity\Mapper::getInstance();
        $this->pastoralSeverities = $pastoralSeverityMapper->findAll()->indexBy('name');

        $roleMapper  = RoleMapper::getInstance();
        $this->roles = $roleMapper->findAll()->indexBy('name');

        $pastoralGroupMapper  = Pastoral\Group\Mapper::getInstance();
        $this->pastoralGroups = $pastoralGroupMapper->findAll()->indexBy('name');

        // Attachment dir
        $dirName = $this->getOpt(self::OPT_LONG_DIR);
        if (!file_exists($dirName)) {
            $this->getLogger()->warning("Unable to locate directory: {$dirName}");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $this->dirName = $dirName;

        // Import csv
        $file = $this->getOpt(self::OPT_LONG_FILE);

        $reader = new Reader($file, true, false);
        if (!$reader->readHeaderRow()) {
            $this->getLogger()->warning("Unable to read header from the file: {$file}");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $reader->setExpectedColumns(
            [
                'SynergeticStudentID',
                'Title',
                'PastoralType',
                'PastoralSubType',
                'Severity',
                'Points',
                'Tags',
                'General',
                'Confidential',
                'linked',
                'Created',
                'CreatedBySynergeticID',
                'FileAttachment',
                'LastUpdated',
                'LastUpdatedBySynergeticID',
                'GeneralVisibility (Role)',
                'ConfidentialVisibility (Pastoral Group)',
            ]
        );
        $reader->next(); // header

        $reader->next();
        $count = 1;
        $this->pastoralMapper->suppressNotification();
        $this->pastoralAccessMapper->suppressNotification();
        $this->pastoralAttachmentMapper->suppressNotification();
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
            $this->getLogger()->info("{$count} records will be processed.");
            $this->userMapper->getDatabase()->rollback();
        }
    }

    /**
     * @param array $row
     */
    private function process(array $row)
    {
        $pastoral = new Pastoral\Model();

        try {
            $pastoral->setStudent($this->userMapper->findByExternalId(trim($row['SynergeticStudentID'])));
        } catch (Mapper\Exception $e) {
            throw new InvalidArgumentException(sprintf('Could not find student #%d', $row['SynergeticStudentID']));
        }

        $pastoral->setHeading(trim($row['Title']));

        // Pastoral type
        $pastoralTypeName = !empty($row['PastoralType']) && key_exists(trim($row['PastoralType']), self::PASTORAL_TYPE_MAP)
            ? self::PASTORAL_TYPE_MAP[trim($row['PastoralType'])] : self::DEFAULT_PASTORAL_TYPE;
        $pastoralType     = $this->pastoralTypes->offsetGet($pastoralTypeName);
        if (!$pastoralType) {
            throw new InvalidArgumentException(sprintf('Could not find pastoral type %s', $pastoralTypeName));
        }
        $pastoral->setType($pastoralType);

        // Pastoral Subtype
        if ($pastoralType->getSubTypes()->count() > 0) {
            $pastoralSubTypes    = $pastoralType->getSubTypes()->indexBy('name');
            $pastoralSubTypeName = !empty($row['PastoralSubType']) && key_exists(trim($row['PastoralSubType']), self::PASTORAL_SUBTYPE_MAP)
                ? self::PASTORAL_SUBTYPE_MAP[trim($row['PastoralSubType'])] : self::DEFAULT_PASTORAL_SUBTYPE;
            $pastoralSubType     = $pastoralSubTypes->offsetGet($pastoralSubTypeName);
            if (!$pastoralSubType) {
                $pastoralSubType = $pastoralSubTypes->offsetGet(self::DEFAULT_PASTORAL_SUBTYPE);
                if (!$pastoralSubType) {
                    throw new InvalidArgumentException(sprintf('Could not find pastoral subtype %s for type %s', trim($row['PastoralSubType']), $pastoralType->getName()));
                }
            }
            $pastoral->setSubType($pastoralSubType);
        }

        // Pastoral severity
        $pastoralSeverityName = !empty($row['PastoralSeverity']) ? trim($row['PastoralSeverity']) : self::DEFAULT_PASTORAL_SEVERITY;
        $pastoralSeverity     = $this->pastoralSeverities->offsetGet($pastoralSeverityName);
        if (!$pastoralSeverity) {
            throw new InvalidArgumentException(sprintf('Could not find pastoral severity %s', $pastoralSeverityName));
        }
        $pastoral->setSeverity($pastoralSeverity);

        // Points
        if (is_numeric(trim($row['Points']))) {
            $pastoral->setPoints((int)$row['Points']);
        }

        // Tags - not supported

        $pastoral->setBodyPublic('<p>' . trim($row['General']) . '</p>');
        $pastoral->setBodyPrivate('<p>' . trim($row['Confidential']) . '</p>');

        // Linked - not supported

        $pastoral->setCreatedAt(\Alaress_Date_Format::newLocalShort(trim($row['Created'])));

        try {
            $pastoral->setOwner($this->userMapper->findByExternalId(trim($row['CreatedBySynergeticID'])));
        } catch (Mapper\Exception $e) {
            throw new InvalidArgumentException(sprintf('Could not find owner %s', $row['CreatedBySynergeticID']));
        }

        $updatedAt = \Alaress_Date_Format::newLocalShort(trim($row['LastUpdated']));
        $pastoral->setUpdatedAt($updatedAt);

        try {
            $pastoral->setModifiedBy($this->userMapper->findByExternalId(trim($row['LastUpdatedBySynergeticID'])));
        } catch (Mapper\Exception $e) {
            throw new InvalidArgumentException(sprintf('Could not find updated user %s', $row['LastUpdatedBySynergeticID']));
        }

        $this->pastoralMapper->save($pastoral);
        $this->pastoralMapper->resetUpdatedTimestamp($pastoral, $updatedAt);

        $attachmentPrivate = false;

        // Visibility - Public
        $publicVisibility = $this->roles->getOffset(trim($row['GeneralVisibility (Role)']));
        if ($publicVisibility) {
            $pastoralAccess = new Pastoral\Access\Model();
            $pastoralAccess->setRole($publicVisibility);
            $pastoralAccess->setAccess(Pastoral\Access\Mapper::ACCESS_PUBLIC);
            $pastoralAccess->setPastoral($pastoral);
            $this->pastoralAccessMapper->save($pastoralAccess);
        }

        // Visibility - Private
        $privateVisibility = $this->pastoralGroups->getOffset(trim($row['ConfidentialVisibility (Pastoral Group)']));
        if ($privateVisibility) {
            $pastoralAccess = new Pastoral\Access\Model();
            $pastoralAccess->setGroup($privateVisibility);
            $pastoralAccess->setAccess(Pastoral\Access\Mapper::ACCESS_PRIVATE);
            $pastoralAccess->setPastoral($pastoral);
            $this->pastoralAccessMapper->save($pastoralAccess);

            $attachmentPrivate = true;
        }

        // Attachment
        $filename = !empty($row['FileAttachment']) ? trim($row['FileAttachment']) : null;
        if (!empty($filename) && file_exists($this->dirName . '/' . $filename)) {
            $attachment = $this->storageMapper->acceptStream($filename, file_get_contents($this->dirName . '/' . $filename), true);

            /* Assign the storage file to the pastoral record
             */
            $recordAttachment = $this->pastoralAttachmentMapper->createModel()
                                                               ->setPastoral($pastoral)
                                                               ->setStorage($attachment)
                                                               ->setPrivate($attachmentPrivate);
            $this->pastoralAttachmentMapper->save($recordAttachment);
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
