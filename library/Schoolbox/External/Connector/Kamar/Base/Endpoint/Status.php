<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Status implements IStatus
{

    /**
     * @var Api
     */
    private $api;


    /**
     * Status constructor.
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->api = new Api($path);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable(): bool
    {
        /* We cached the file locally so if it exists, then it's reachable
         * Whether or not it contains valid data is a different question altogether
         */
        return $this->api->isXmlFileExists();
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getReport(): string
    {
        $specification = new Specification(null, new Batchxml($this->api));

        try {
            return implode("\n", $specification->check());
        } catch (\RuntimeException $e) {
            return "Cannot access api endpoint: {$e->getMessage()}";
        }
    }
}
