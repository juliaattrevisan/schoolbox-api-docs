<?php
/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile;

use Laminas\Uri\Exception\InvalidUriException;
use Laminas\Uri\Exception\InvalidUriPartException;
use Laminas\Uri\Uri;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use ZipArchive;


/**
 * Class PhotoDownloader
 *
 * Downlaods a zip file containing profile pictures, from isams
 * For some reason the Guzzle implementation results in isams
 * stopping you:
 *
 * "iSAMS blocked the request as it came from an unknown source."
 *
 * The Alaress curl url lets you in though.
 */
class PhotoDownloader
{
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
     * Zip files download here, as per config
     *
     * @var string
     */
    private string $zipDir;


    /**
     * @var string
     */
    private string $key;


    /**
     * Constructor.
     *
     * @param string $host
     * @param string $dsn
     * @param string $zipDir
     * @throws \DomainException
     */
    public function __construct(string $host, string $dsn, string $zipDir) {
        $this->host = $host;
        $this->zipDir = $zipDir;

        /* Store this locally just once
         */
        $credentials = (new DSN())->decode($dsn);

        $this->key = $credentials['modules'][DSN::MODULE_BATCHXML]['key'];
    }


    /**
     * Download student photos
     *
     * @return void
     * @throws InvalidUriException
     * @throws InvalidUriPartException
     */
    public function downloadStudentPhotos(): void
    {
        $this->download('photos.ashx', $this->key);
    }


    /**
     * Download staff photos
     *
     * @return void
     * @throws InvalidUriException
     * @throws InvalidUriPartException
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
     * @throws InvalidUriException
     * @throws InvalidUriPartException
     */
    private function download(string $path, string $key): void
    {
        $filename = tempnam($this->zipDir, self::DOWNLOAD_PREFIX);
        $filepointer = fopen($filename, 'wb+');

        $url = (new Uri())
            ->setScheme('https')
            ->setHost(rtrim($this->host, "/ \n\r\t\v\x00"))
            ->setPath("/{$path}")
            ->setQuery(['apikey' => $key]);

        $curl = new \Alaress_Curl_Url($url->toString());
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
            $zipArchive->extractTo($this->zipDir);
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
     * @return string|null
     */
    public function getPhoto(string $personId): ?string
    {
        $path = "{$this->zipDir}/{$personId}.png";

        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return null;
    }
}
