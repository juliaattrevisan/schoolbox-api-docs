#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        // Doesn't matter, not writing any files
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        $dataDirs = [
            'purifier_cache_dir',
            'twig_cache_dir',
            'backup_dir',
            'zip_dir',
            'stream_dir',
            'portrait_dir',
            'logs_dir',
            'storage_dir',
        ];

        foreach ($dataDirs as $dataDir) {
            $value = $this->getConfig()->get($dataDir);

            $realPath = realpath(trim($value));
            if ($realPath === false) {
                $this->getLogger()->error("DataDir '$dataDir' with value '$value' appears to not exist");
                continue;
            }
            if ($realPath === $value) {
                $this->getLogger()->debug("DataDir '$dataDir' with value '$value' appears to already be normalised");
                continue;
            }

            $sql = "UPDATE config SET value = :value WHERE name = :name";
            $this->getDatabaseInternal()->executeQuery(
                $sql,
                [
                    'value' => $realPath,
                    'name'  => $dataDir,
                ]);
            $this->getLogger()->notice("DataDir '$dataDir' with value '$value' normalised to '$realPath'");
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
