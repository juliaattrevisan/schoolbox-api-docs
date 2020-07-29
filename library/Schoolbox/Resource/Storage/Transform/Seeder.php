<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use Aws\S3\S3Client;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;


class Seeder extends App
{
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var string
     */
    private $bucket;

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help'];
    }

    /**
     * Hands off execution over to the App
     */
    public function run(): void
    {
        $this->getLogger()->info('Storage transform data seeder');

        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(Kernel::EXIT_STATUS_OK);
        }

        $this->buildDependencies();
   }


   private function buildDependencies(): void
   {
       $config = $this->getConfig();

       $this->s3Client         = new S3Client(
           [
               'credentials' => [
                   'key'    => trim($config->get('otfs_s3_cred_key') ?? ''),
                   'secret' => trim($config->get('otfs_s3_cred_secret') ?? ''),
               ],
               'region'      => trim($config->get('otfs_s3_region') ?? ''),
               'version'     => trim($config->get('otfs_s3_version') ?? ''),
           ]
       );

       $this->bucket    = trim($config->get('otfs_s3_bucket') ?? '');
   }

   private function createStorageTransformSchema()
   {
       //TODO: Create storage_transform table if it doesn't exists
   }

    /**
     * Print a help message of available options
     */
    private function printHelp(): void
    {
        echo "Options: 
    -h, --help
        Print help message and exit.
    -p, --perform
        If set, the tool will persist data to `storage_transform` table
";
    }
}
