<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Setup
 *
 * Set up a special cache folder for all the kamar xmls
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Setup implements IStrategy
{

    /**
     * @var string
     */
    private const KAMAR_CACHE_DIR = 'kamar';


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var string
     */
    private $cacheDir;


    /**
     * Setup constructor.
     *
     * @param LoggerInterface|null $log
     * @param string               $streamDir
     */
    public function __construct(?LoggerInterface $log, string $streamDir)
    {
        $this->log = $log;
        $this->cacheDir = $streamDir . '/' . self::KAMAR_CACHE_DIR;
    }


    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }


    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function import(): void
    {
        if (!is_dir($this->cacheDir)) {
            if ($this->log) {
                $this->log->info('Creating new kamar xml cache dir');
            }

            /* storm autocompleted this suggestion for me
             */
            if (!mkdir($concurrentDirectory = $this->cacheDir) && !is_dir($concurrentDirectory)) {
                $message = sprintf('Directory "%s" was not created', $concurrentDirectory);

                if ($this->log) {
                    $this->log->critical($message);
                }

                throw new RuntimeException($message);
            }
        }
    }
}
