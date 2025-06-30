<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\Role;
use Schoolbox\Url\Uri;
use SensitiveParameter;
use ZipArchive;

/**
 * Class PhotoDownloader
 *
 * Downloads a zip file containing profile pictures, from isams
 * For some reason the Guzzle implementation results in isams
 * stopping you:
 *
 * "iSAMS blocked the request as it came from an unknown source."
 *
 * The Alaress curl url lets you in though.
 */
class BatchPhotoDownloader implements IPhotoDownloader
{
    /**
     * A subfolder of $zipDir gets created forcibly
     *
     * @var string
     */
    public const ISAMS_CACHE_DIR = 'isams';


    /**
     * All downloads have this prefix
     */
    private const DOWNLOAD_PREFIX = 'isams_profiles_';


    /**
     * The host of the iSAMS server
     *
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $key;


    /**
     * @var string
     */
    private string $cacheDir;


    /**
     * @var array
     */
    private array $extractionPaths;


    /**
     * Constructor.
     *
     * @param string $host
     * @param array $credentials
     * @param string $zipDir
     */
    public function __construct(string $host, #[SensitiveParameter] array $credentials, string $zipDir)
    {
        $this->host = $host;

        /* Store this locally just once
         */
        $this->key = $credentials['modules'][DSN::MODULE_BATCHXML]['key'];

        $this->cacheDir = $zipDir . '/' . self::ISAMS_CACHE_DIR;

        /* Make subfolders
         */
        $this->extractionPaths = [
            Role::STUDENT => sprintf(
                '%s/%s',
                $this->cacheDir,
                Role::STUDENT
            ),
            Role::STAFF => sprintf(
                '%s/%s',
                $this->cacheDir,
                Role::STAFF
            ),
        ];
    }


    /**
     * Download student photos
     *
     * @return void
     * @throws \RuntimeException
     */
    public function downloadStudentPhotos(): void
    {
        $this->download('photos.ashx', $this->key, Role::STUDENT);
    }


    /**
     * Download staff photos
     *
     * @return void
     * @throws \RuntimeException
     */
    public function downloadStaffPhotos(): void
    {
        $this->download('staffphotos.ashx', $this->key, Role::STAFF);
    }


    /**
     * Constructs a zip file on the local filesystem
     *
     * @param string $path
     * @param string $key
     * @param string $type
     * @return void
     */
    private function download(string $path, string $key, string $type): void
    {
        /* Forcibly create isams subdir
         */
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($concurrentDirectory = $this->cacheDir) && !is_dir($concurrentDirectory)) {
                $message = sprintf('Directory "%s" was not created', $concurrentDirectory);

                throw new \RuntimeException($message);
            }

            foreach ($this->extractionPaths as $extractionPath) {
                if (!mkdir($extractionPath) && !is_dir($extractionPath)) {
                    $message = sprintf('Directory "%s" was not created', $extractionPath);

                    throw new \RuntimeException($message);
                }
            }
        }

        /* Shove in the files we download
         */
        $filename = tempnam($this->cacheDir, self::DOWNLOAD_PREFIX);
        $filepointer = fopen($filename, 'wb+');

        $url = (new Uri())
            ->withScheme('https')
            ->withHost(rtrim($this->host, "/ \n\r\t\v\x00"))
            ->withPath("/{$path}")
            ->appendQueryParameters(['apikey' => $key]);

        $curl = new \Alaress_Curl_Url((string)$url);
        $curl->exec();

        if (empty($curl->getErrors())) {
            // Too big to parse
            $data = $curl->getResult();
            fwrite($filepointer, $data);
        }

        fclose($filepointer);

        $this->extract($filename, $type);
    }


    /**
     * Extract to zip dir for now I suppose
     *
     * @param string $filename
     * @param string $type
     * @return void
     */
    private function extract(string $filename, string $type): void
    {
        $zipArchive = new ZipArchive();

        $result = $zipArchive->open($filename);

        if (true === $result) {
            /* Student and Staff zips might contain files with the same filename, OR, as we found,
             * the staff's PersonId happens to match a student's PersonId, causing them to have
             * the same picture. We split the downloads into subdirectories to deconflict them.
             */
            $extractionPath = $this->extractionPaths[$type];

            $zipArchive->extractTo($extractionPath);
            $zipArchive->close();
        }
    }


    /**
     * Get a photo's contents after decide if $personId has a picture
     * that came out of the zip archive. Returns null if the file
     * doesn't exist
     *
     * All isams pictures seem to be pngs in a zip (god knows why)
     *
     * @param array $data
     * @return string|null
     */
    public function getPhoto(array $data): ?string
    {
        /* Batchxml photos are named after a person's isams internal identifier, not their school id
         * TODO verify staff pictures are also named the same way
         */
        $roleType = $data['type'];
        $identifier = $data['internalId'];

        $path = "{$this->cacheDir}/{$roleType}/{$identifier}.png";

        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return null;
    }


    /**
     * @inheritDoc
     */
    public function getLatestPhotoId(array $data): ?int
    {
        return null;
    }
}
