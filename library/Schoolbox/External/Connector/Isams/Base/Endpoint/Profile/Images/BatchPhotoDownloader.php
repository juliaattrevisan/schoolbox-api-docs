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
     * Constructor.
     *
     * @param string $host
     * @param array $credentials
     * @param string $zipDir
     * @throws \DomainException
     */
    public function __construct(string $host, #[SensitiveParameter] array $credentials, string $zipDir)
    {
        $this->host = $host;

        /* Store this locally just once
         */
        $this->key = $credentials['modules'][DSN::MODULE_BATCHXML]['key'];

        $this->cacheDir = $zipDir . '/' . self::ISAMS_CACHE_DIR;
    }


    /**
     * Download student photos
     *
     * @return void
     * @throws \RuntimeException
     */
    public function downloadStudentPhotos(): void
    {
        $this->download('photos.ashx', $this->key);
    }


    /**
     * Download staff photos
     *
     * @return void
     * @throws \RuntimeException
     */
    public function downloadStaffPhotos(): void
    {
        $this->download('staffphotos.ashx', $this->key);
    }


    /**
     * Constructs a zip file on the local filesystem
     *
     * @param string $path
     * @param string $key
     * @return void
     * @throws \RuntimeException
     */
    private function download(string $path, string $key): void
    {
        /* Forcibly create isams subdir
         */
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($concurrentDirectory = $this->cacheDir) && !is_dir($concurrentDirectory)) {
                $message = sprintf('Directory "%s" was not created', $concurrentDirectory);

                throw new \RuntimeException($message);
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

        $this->extract($filename);
    }


    /**
     * Extract to zip dir for now I suppose
     *
     * @param string $filename
     * @return void
     */
    private function extract(string $filename): void
    {
        $zipArchive = new ZipArchive();

        $result = $zipArchive->open($filename);

        if (true === $result) {
            $zipArchive->extractTo($this->cacheDir);
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
     * @param string $personId
     * @param string $roleType
     * @param ?string $externalId = null
     * @return string|null
     */
    public function getPhoto(string $personId, string $roleType = Role::STUDENT, ?string $externalId = null): ?string
    {
        $path = "{$this->cacheDir}/{$personId}.png";

        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return null;
    }


    /**
     * Removes all the photo-related files from the isams cache dir
     *
     * @return void
     */
    public function cleanup(): void
    {
        $dirhandle = opendir($this->cacheDir);

        while ($file = readdir($dirhandle)) {
            if ($file !== '.' && $file !== '..') {
                $source = "{$this->cacheDir}/{$file}";

                unlink($source);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getLatestPhotoID(string $roleType = Role::STUDENT, string|int|null $externalId = null): ?int
    {
        return null;
    }
}
