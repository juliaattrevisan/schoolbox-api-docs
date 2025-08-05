#!/usr/bin/env php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Simple script for converting vagrant.yaml to a .env file, and outputting to
 * stdout.
 */
$kernel = new CliTool\Kernel(new class extends CliTool\App {
    /**
     * The vagrant.yaml file to translate.
     */
    private const VAGRANT_FILE = __DIR__ . '/../vagrant.yaml';

    /**
     * The mapping of keys in the vagrant.yaml file to env vars.
     *
     * Null values are not used in Docker's provision process, and are thus not
     * mapped.
     *
     * @var array<string, ?string>
     */
    private const KEY_MAPPING = [
        'vagrant_box'                    => null,
        'memory'                         => null,
        'processors'                     => null,
        'public_network'                 => null,
        'private_network_ip'             => null,
        'external_db'                    => 'EXTERNAL_DB',
        'external_db_host'               => 'EXTERNAL_DB_HOST',
        'external_db_db'                 => 'EXTERNAL_DB_DB',
        'provision_script'               => null,
        'enable_xdebug'                  => null,
        'data_source'                    => 'DATA_SOURCE',
        'data_filename'                  => 'DATA_FILENAME',
        'bridged_network_interface'      => null,
        'puppet_dir'                     => null,
        'puppet_debug'                   => null,
        'instance_registry_url_override' => 'INSTANCE_REGISTRY_URL',
        'instance_registry_api_key'      => 'INSTANCE_REGISTRY_API_KEY',
        'instance_id_env_var'            => 'DEFAULT_INSTANCE_ID',
        'puppet_sync_type'               => null,
        'force_upgrade'                  => null,
        'extra_hostname'                 => null,
        'aws_iam_key'                    => null,
        'aws_iam_secret'                 => null,
        'aws_personal_iam_key'           => null,
        'aws_personal_iam_secret'        => null,
        'primary_php_majver'             => null,
        'taskd_dir'                      => null,
        'taskd_sync_type'                => null,
        'taskd_build_from_local'         => 'TASKD_BUILD_FROM_LOCAL',
        'network_auto_config'            => null,
        'api_dir'                        => 'API_DIR',
        'aws_account_id'                 => 'AWS_ACCOUNT_ID',
        'aws_role_name'                  => 'AWS_ROLE_NAME',
        'aws_start_url'                  => 'AWS_ROLE_NAME',
        'aws_bucket'                     => 'CDN_BUCKET',
        'aws_bucket_prefix'              => 'CDN_BUCKET_PREFIX',
    ];

    /**
     * Format for a line in the env file.
     */
    private const ENV_FILE_FORMAT = "%s=%s";

    /**
     * @return void
     */
    public function run(): void
    {
        $output = [];
        $log    = $this->getLogger();
        if (!file_exists(self::VAGRANT_FILE)) {
            $log->warning('vagrant.yaml does not exist: nothing to do');
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        $yaml = Yaml::parse(file_get_contents(self::VAGRANT_FILE));
        foreach ($yaml as $oldKey => $value) {
            $log->debug(sprintf('Key "%s" found in vagrant.yaml', $oldKey));

            if (!array_key_exists($oldKey, self::KEY_MAPPING)) {
                $log->debug('.. not found in key mapping: skipping');
                continue;
            }

            $newKey = self::KEY_MAPPING[$oldKey];
            if (!isset($newKey)) {
                $log->debug('.. does not map to any key in Docker env: skipping');
                continue;
            }

            if (!is_scalar($value)) {
                $log->warning(sprintf('Key "%s" is not a scalar value: skipping', $oldKey));
                continue;
            }

            if (is_bool($value)) {
                $newValue = $value ? 1 : 0;
                $log->debug(sprintf('.. mapping boolean %s to "%s"', $value ? 'true' : 'false', $newValue));
                $value = $newValue;
            }

            $log->debug(sprintf('.. mapping to new key "%s"', $newKey));
            $output[] = sprintf(
                self::ENV_FILE_FORMAT,
                $newKey,
                is_string($value) ? escapeshellarg($value) : (string)$value,
            );
        }
        echo implode(PHP_EOL, $output) . PHP_EOL;
        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }
});
$kernel->runApp();
