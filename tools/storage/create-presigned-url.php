#!/usr/bin/php
<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Simple helper to generate a presigned URL
 *
 * Eg, if we need to provide a user-friendly URL for a client, eg during decommission to their storage+db dump files
 */
$app    = new class extends CliTool\App {
    private const OPT_SHORT_HELP      = 'h';
    private const OPT_LONG_HELP       = 'help';
    private const OPT_LONG_OBJECT_KEY = 'key:';
    private const OPT_LONG_EXPIRY     = 'expiry:';

    public function getShortOpts(): array
    {
        return array_merge(
            [self::OPT_SHORT_HELP],
            parent::getShortOpts()
        );
    }

    public function getLongOpts(): array
    {
        return array_merge(
            [self::OPT_LONG_HELP, self::OPT_LONG_OBJECT_KEY, self::OPT_LONG_EXPIRY,],
            parent::getLongOpts()
        );
    }

    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        $objectKey = $this->getOpt(self::OPT_LONG_OBJECT_KEY);
        $errors    = false;
        if (($objectKey ?? '') === '') {
            $this->getLogger()->error(sprintf("You must specify an object key using the --%s flag", trim(self::OPT_LONG_OBJECT_KEY, ':')));
            $errors = true;
        }
        $expiry = $this->getOpt(self::OPT_LONG_EXPIRY);
        if ($expiry === null) {
            $this->getLogger()->error(sprintf("You must specify an expiry period using the --%s flag", trim(self::OPT_LONG_EXPIRY, ':')));
            $errors = true;
        } elseif (strtotime($expiry) === false) {
            $this->getLogger()->error("Invalid expiry '$expiry'");
            $errors = true;
        }
        if ($errors) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
        $this->generatePresignedUrl($objectKey, $expiry);

        $this->deprecationNotice("SA-19334");
    }


    /**
     * Generates an presigned URL and writes it to STDOUT
     * @param string $objectKey
     * @param string $expiry
     */
    private function generatePresignedUrl(string $objectKey, string $expiry): void
    {
        $s3Client = $this->buildS3Client();

        $s3AltHost   = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? "");
        $s3AltBucket = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? "");
        $bucket      = !empty($s3AltHost) && !empty($s3AltBucket)
            ? $s3AltBucket
            : $this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_BUCKET);

        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $objectKey,
        ]);

        $url = $s3Client->createPresignedRequest($command, $expiry)->getUri()->__toString();
        fwrite(STDOUT, "$url\n");
    }

    /**
     * Build and return an S3 client
     *
     * @return S3Client
     */
    private function buildS3Client(): S3Client
    {
        $altHost = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? "");
        $s3AltAccessKey = trim($this->getConfig()->get('otfs_s3_alt_access_key') ?? "");
        $s3AltSecretAccessKey = trim($this->getConfig()->get('otfs_s3_alt_secret_key') ?? "");
        $altBucket = trim($this->getConfig()->get('otfs_s3_alt_bucket') ?? "");
        $s3AltRegion = trim($this->getConfig()->get('otfs_s3_alt_region') ?? "");
        $cfg = [
            'credentials' => [
                'key'    => trim($this->getConfig()->get('otfs_s3_cred_key') ?? ""),
                'secret' => trim($this->getConfig()->get('otfs_s3_cred_secret') ?? ""),
            ],
            'region'  => trim($this->getConfig()->get('otfs_s3_region') ?? ""),
            'version' => trim($this->getConfig()->get('otfs_s3_version') ?? ""),
        ];

        if (!empty($altHost)) {
            $cfg['endpoint'] = $altHost;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretAccessKey;
            $cfg['region'] = $s3AltRegion;
        }
        return new S3Client($cfg);
    }

    /**
     * Prints a help message to STDOUT
     */
    private function printHelp(): void
    {
        $optNameKey       = trim(self::OPT_LONG_OBJECT_KEY, ':');
        $optNameExpiry    = trim(self::OPT_LONG_EXPIRY, ':');
        $optNameLongHelp  = self::OPT_LONG_HELP;
        $optNameShortHelp = self::OPT_SHORT_HELP;

        echo "
DESCRIPTION:
  A simple script to generate a presigned URL to a file in AWS S3.

USAGE:
  {$this->getToolFilename()} --$optNameKey [KEY] --$optNameExpiry [EXPIRY]

OPTIONS:
  --$optNameLongHelp, -$optNameShortHelp
    Print help message and exit
  --$optNameKey
    The S3 object key
  --$optNameExpiry
    The period for which the URL should be valid. Accepts anything accepted by PHP's strtotime()

EXAMPLES:
  {$this->getToolFilename()} --$optNameKey storage/60ad3/80736/fdb07/1ad4c/fceef/b0061/66a3d/d5791/60ad380736fdb071ad4cfceefb006166a3dd5791 --$optNameExpiry '1 week'

";
    }
};
$kernel = new CliTool\Kernel($app);
$kernel->runApp();
