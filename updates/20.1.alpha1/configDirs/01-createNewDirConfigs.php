#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Needs to be PHP because we need to create the directories on disk.
 *
 * Note that this script only runs on the primary host in the cluster, non-primary hosts will need puppet
 * to create the directories on disk by reading their values from the config table.
 */
$app = new class extends CliTool\App {

    /** @inheritDoc */
    public function run(): void
    {
        $this->createNewDirs();
        $this->deleteObsoleteDirConfigs();
    }


    /**
     * Creates new data_dir in config table + on disk
     */
    private function createNewDirs(): void
    {
        $xmlDir = $this->getConfig()->get("xml_dir");
        $this->getLogger()->info("'xml_dir' => '$xmlDir'");

        $this->createPurifierDirectory($xmlDir);
        $this->createTwigDirectory($xmlDir);
        $this->renameFileDirToBackupDir();
    }


    /**
     * Create 'purifier_cache_dir' in config table and on disk
     *
     * @param string $xmlDir
     */
    private function createPurifierDirectory(string $xmlDir): void
    {
        // Add Purifier dir (adjacent to xml_dir)
        $purifierCacheDir = dirname($xmlDir) . "/purifier/";
        if (!mkdir($purifierCacheDir) && !is_dir($purifierCacheDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $purifierCacheDir));
        }
        $sql = "
        INSERT INTO `config` (
            `name`,
            `value`,
            `title`,
            `description`,
            `field_id`,
            `options`,
            `editable`,
            `hidden`,
            `required`,
            `module`
        )
        SELECT
            'purifier_cache_dir',
            :value,
            'HTMLPurifier Cache Location',
            'Location of HTMLPurifier caches. Cleared on version update',
            c2.field_id,
            c2.options,
            c2.editable,
            c2.hidden,
            c2.required,
            c2.module
        FROM config c2
        WHERE c2.name = 'xml_dir';
        ";
        $this->getDatabaseInternal()->executeQuery($sql, ['value' => $purifierCacheDir]);
        $this->getLogger()->notice("Create new 'purifier_cache_dir' at '$purifierCacheDir'");
    }


    /**
     * Create 'twig_cache_dir' in config table + on disk
     *
     * @param string $xmlDir
     */
    private function createTwigDirectory(string $xmlDir): void
    {
        // Twig Cache dir (adjacent to xml_dir)
        $twigCacheDir = dirname($xmlDir) . "/twig/";
        if (!mkdir($twigCacheDir) && !is_dir($twigCacheDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $twigCacheDir));
        }
        $sql = "
        INSERT INTO `config` (
            `name`,
            `value`,
            `title`,
            `description`,
            `field_id`,
            `options`,
            `editable`,
            `hidden`,
            `required`,
            `module`
        )
        SELECT
            'twig_cache_dir',
            :value,
            'Twig Cache Location',
            'Location of Twig caches. Cleared on version update',
            c2.field_id,
            c2.options,
            c2.editable,
            c2.hidden,
            c2.required,
            c2.module
        FROM config c2
        WHERE c2.name = 'xml_dir';
        ";
        $this->getDatabaseInternal()->executeQuery($sql, ['value' => $twigCacheDir]);
        $this->getLogger()->notice("Create new 'twig_cache_dir' at '$twigCacheDir'");
    }

    /**
     * Rename 'file_dir' to 'backup_dir'
     */
    private function renameFileDirToBackupDir(): void
    {
        // Backup dir: the same as file_dir for now
        $sql = "
        UPDATE config
        SET
            name        = 'backup_dir',
            title       = 'Backup Location',
            description = 'Location of Schoolbox database backups.'
        WHERE name = 'file_dir';";
        $this->getDatabaseInternal()->execute($sql);
    }


    /**
     * Deletes old config.
     */
    private function deleteObsoleteDirConfigs(): void
    {
        // These configs are no longer needed, so they can be removed
        $sql = "
            DELETE FROM config WHERE name IN (
                'template_dir',
                'myfile_dir',
                'submission_dir',
                'returns_dir',
                'dropbox_dir',
                'thumbnail_dir',
                'xml_dir',
                'weather_dir',
                'minify_dir',
                'myfiles_cache_dir',
                'video_dir',
                'submission_upload_tmp_dir'
            );";
        $this->getDatabaseInternal()->execute($sql);
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
