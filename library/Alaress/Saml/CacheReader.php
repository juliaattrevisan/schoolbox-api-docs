<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Saml;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\FilesystemCache;

/**
 * Filesystem cache driver.
 * Ported read functionality from FilesystemCache
 */
class CacheReader extends CacheProvider
{

    /**
     * The cache directory.
     *
     * @var string
     */
    private $directory;

    /**
     * The cache file extension.
     *
     * @var string
     */
    private $extension;

    /** @var int */
    private $directoryStringLength;

    /** @var int */
    private $extensionStringLength;

    /** @var bool */
    private $isRunningOnWindows;

    public function __construct($directory)
    {
        $this->directory             = realpath($directory);
        $this->extension             = FilesystemCache::EXTENSION;
        $this->directoryStringLength = strlen($this->directory);
        $this->extensionStringLength = strlen($this->extension);
        $this->isRunningOnWindows    = defined('PHP_WINDOWS_VERSION_BUILD');
    }


    /**
     * @see FilesystemCache::doFetch()
     */
    protected function doFetch($id)
    {
        $data     = '';
        $lifetime = -1;
        $filename = $this->getFilename($id);

        if (!is_file($filename)) {
            return false;
        }

        $resource = fopen($filename, 'r');
        $line     = fgets($resource);

        if ($line !== false) {
            $lifetime = (int)$line;
        }

        if ($lifetime !== 0 && $lifetime < time()) {
            fclose($resource);

            return false;
        }

        while (($line = fgets($resource)) !== false) {
            $data .= $line;
        }

        fclose($resource);

        return unserialize($data);
    }

    /**
     * @see FilesystemCache::doContains()
     */
    protected function doContains($id)
    {
        $lifetime = -1;
        $filename = $this->getFilename($id);

        if (!is_file($filename)) {
            return false;
        }

        $resource = fopen($filename, 'r');
        $line     = fgets($resource);

        if ($line !== false) {
            $lifetime = (int)$line;
        }

        fclose($resource);

        return $lifetime === 0 || $lifetime > time();
    }

    /**
     * {@inheritDoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doDelete($id)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doFlush()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doGetStats()
    {
        return;
    }

    /**
     * @see FilesystemCache::getFilename()
     */
    protected function getFilename($id)
    {
        $hash = hash('sha256', $id);

        // This ensures that the filename is unique and that there are no invalid chars in it.
        if ($id === ''
            || ((strlen($id) * 2 + $this->extensionStringLength) > 255)
            || ($this->isRunningOnWindows && ($this->directoryStringLength + 4 + strlen($id) * 2 + $this->extensionStringLength) > 258)
        ) {
            // Most filesystems have a limit of 255 chars for each path component. On Windows the the whole path is limited
            // to 260 chars (including terminating null char). Using long UNC ("\\?\" prefix) does not work with the PHP API.
            // And there is a bug in PHP (https://bugs.php.net/bug.php?id=70943) with path lengths of 259.
            // So if the id in hex representation would surpass the limit, we use the hash instead. The prefix prevents
            // collisions between the hash and bin2hex.
            $filename = '_' . $hash;
        } else {
            $filename = bin2hex($id);
        }

        return $this->directory
            . DIRECTORY_SEPARATOR
            . substr($hash, 0, 2)
            . DIRECTORY_SEPARATOR
            . $filename
            . $this->extension;
    }

}
