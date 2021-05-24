#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\File\MimetypeMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App {

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
        return [
            self::OPT_LONG_HELP
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Updates the mime types for RTF and AVI files. These use application/rtf and video/x-msvideo mimetypes.
If they already exist in an Allowed Files table, nothing changes.

HELP;
            exit;
        }

        $mapper = MimetypeMapper::getInstance();

        /* application/rtf
         */
        $this->getLogger()->info('Trying to update text/richtext mimetype to application/rtf');

        $this->updateByMimeType(
            $mapper,
            'text/richtext',
            'application/rtf',
            'Rich Text',
            'application-text.svg',
            true
        );

        /* video/x-msvideo
         */
        $this->getLogger()->info('Trying to update video/avi mimetype to video/x-msvideo');

        $this->updateByMimeType(
            $mapper,
            'video/avi',
            'video/x-msvideo',
            'AVI Video',
            'application-video.svg',
            false
        );
    }


    /**
     * Create/update a mime type
     *
     * @param MimetypeMapper $mapper
     * @param string         $oldType
     * @param string         $newType
     * @param string         $description
     * @param string         $iconFile
     * @param bool           $inline
     * @return void
     */
    public function updateByMimeType(
        MimetypeMapper $mapper,
        string $oldType,
        string $newType,
        string $description,
        string $iconFile,
        bool $inline
    ): void {
        $existing = null;

        /* Find out if this $newType row already exists in Allowed Files.
         * If it already exists, we don't need to do anything.
         */
        try {
            $existing = $mapper->findOneByColumn('mime', $newType, [
                'deleted' => true
            ]);
        } catch (\Schoolbox\Mapper\Exception\NotFound $e) {
            // noop
            $this->getLogger()->info(sprintf(
                'Mimetype %s not found; continuing to look for %s to see if we can update it.',
                $newType,
                $oldType
            ));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            $this->getLogger()->info(sprintf(
                'Mimetype %s already exists but is already deleted; cannot add it again. No change required.',
                $newType
            ));

            return;
        }

        if ($existing) {
            $this->getLogger()->info(sprintf(
                'Mimetype %s already exists; no change required.',
                $newType
            ));

            return;
        }

        /* Otherwise, we need to find the old version $oldType. If that exists,
         * update it with $newType instead and leave all the other settings intact.
         */
        try {
            $existing = $mapper->findOneByColumn('mime', $oldType, [
                'deleted' => true
            ]);
        } catch (\Schoolbox\Mapper\Exception\NotFound $e) {
            // noop
            $this->getLogger()->info(sprintf(
                'Mimetype %s also not found; will need to make a new row.',
                $oldType
            ));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            // noop; row was softdeleted, but we should update that anyway

            $this->getLogger()->info(sprintf(
                'Mimetype %s already exists but is already deleted; updating it anyway.',
                $oldType
            ));

            $existing = $e->getDeletedObject();
        }

        if ($existing) {
            $existing->setMime($newType);

            $mapper->save($existing);

            $this->getLogger()->info(sprintf(
                'Mimetype %s found, updating it to %s.',
                $oldType,
                $newType
            ));

            return;
        }

        /* If $oldType doesn't exist for some reason, create a new row with $newType
         * and some default information about $newType file types.
         */
        $new = $mapper->createModel()
            ->setDescription($description)
            ->setMime($newType)
            ->setIconFile($iconFile)
            ->setInline($inline)
            ->setEnable(true)
            ->setMaxFileSize(0);

        $mapper->save($new);

        $this->getLogger()->info(sprintf(
            'Mimetype %s getting created.',
            $newType
        ));
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
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [ CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL, ];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
