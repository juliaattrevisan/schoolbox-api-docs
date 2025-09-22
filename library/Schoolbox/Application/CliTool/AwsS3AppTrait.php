<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Aws\S3\S3Client;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;

/**
 * These tools need to load all files from AWS S3 and perform some operation on them.
 *
 * This class contains that common functionality
 */
trait AwsS3AppTrait
{
    /**
     * @param LoggerInterface $log
     * @param IConfigLoader   $config
     * @return S3Client
     * @throws InvalidArgumentException
     */
    private function buildS3ClientOrExit(LoggerInterface $log, IConfigLoader $config): S3Client
    {
        $cfg = $this->buildS3Config($config, $log);
        return new S3Client($cfg);
    }

    private function buildS3Config(IConfigLoader $config, LoggerInterface $log): array
    {
        $s3AccessKey    = trim($config->get(ConfigMapper::NAME_OTFS_S3_CRED_KEY) ?? "");
        $s3SecretKey    = trim($config->get(ConfigMapper::NAME_OTFS_S3_CRED_SECRET) ?? "");
        $s3Region       = trim($config->get(ConfigMapper::NAME_OTFS_S3_REGION) ?? "");
        $s3Version      = trim($config->get(ConfigMapper::NAME_OTFS_S3_VERSION) ?? "");
        $s3Bucket       = trim($config->get(ConfigMapper::NAME_OTFS_S3_BUCKET) ?? "");
        $s3AltHost      = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? "");
        $s3AltAccessKey = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_ACCESS_KEY) ?? "");
        $s3AltSecretKey = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_SECRET_KEY) ?? "");
        $s3AltBucket    = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? "");
        $s3AltRegion    = trim($config->get(ConfigMapper::NAME_OTFS_S3_ALT_REGION) ?? "");

        $log->debug(sprintf("OTFS Adapter: '%s'", $config->get(ConfigMapper::NAME_OTFS_ADAPTER)));
        $log->debug("S3 Access Key: '$s3AccessKey'");
        $log->debug(sprintf("S3 Secret Key: '%s'", str_pad("", strlen($s3SecretKey), "*")));
        $log->debug("S3 Region: '$s3Region'");
        $log->debug("S3 Version: '$s3Version'");
        $log->debug("S3 Alt Host: '$s3AltHost'");

        $finalBucket = !empty($s3AltHost) && !empty($s3AltBucket) ? $s3AltBucket : $s3Bucket;
        $log->debug("S3 Bucket: '$finalBucket'");

        if (empty($s3AccessKey) || empty($s3SecretKey) || empty($s3Region) || empty($s3Version) || empty($finalBucket)) {
            $log->error("Error: S3 config appears invalid");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $cfg = [
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
        ];

        if (!empty($s3AltHost)) {
            $cfg['endpoint'] = $s3AltHost;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretKey;
            $cfg['region'] = $s3AltRegion;
        }

        return $cfg;
    }

    /**
     * Fetch the keys of all files found in the bucket in an array where the key is the element index
     * and value is file size in bytes
     *
     * @param LoggerInterface $log
     * @param S3Client        $s3Client
     * @param string          $bucketName
     * @return int[]
     */
    protected function getFilesInS3(LoggerInterface $log, S3Client $s3Client, string $bucketName): array
    {
        $filesInS3 = [];
        $log->notice("Fetching file list from S3, this may take a while on large datasets...");
        foreach (
            $s3Client->getIterator('ListObjects', [
                'Bucket' => $bucketName,
                'Prefix' => FilesystemFactory::PREFIX_STORAGE,
            ]) as $i => $object
        ) {
            if ($i && $i % 1000 === 0) {
                $log->debug("Files found in S3: $i...");
            }
            $filesInS3[$object['Key']] = (int)$object['Size'];
        }
        return $filesInS3;
    }
}
