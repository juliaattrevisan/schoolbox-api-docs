<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Status\IStatus;
use Zend\Uri\Uri;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Status implements IStatus
{

    /**
     * @var string
     */
    private $host;


    /**
     * @var string
     */
    private $key;


    /**
     * @var Api
     */
    private $api;


    /**
     * Status constructor.
     *
     * @param string $host
     * @param string $key
     */
    public function __construct(string $host, string $key)
    {
        $this->host = $host;
        $this->key = $key;

        $this->api = new Api($host, $key);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable(): bool
    {
        /* BatchXML is configured to dump you the entire xml feed; you can't just get a portion
         * which means you're downloading boatloads of data just to see if you could
         *
         * Use an ultra cheep way at the cost of some neatness
         */
        $result = get_headers("https://{$this->host}/api/batch/1.0/xml.ashx?apikey={$this->key}");

        if (is_array($result)) {
            return strpos($result[0], '200') > -1;
        }

        return false;
    }


    /**
     * @inheritDoc
     *
     * @return string|null
     */
    public function getReport(): string
    {
        $specification = new Specification(
            null,
            new Batchxml($this->api)
        );

        try {
            return implode("\n", $specification->check());
        } catch (\RuntimeException $e) {
            return "Cannot access api endpoint: {$e->getMessage()}";
        } catch (InvalidUriPartException $e) {
            return "Cannot access api endpoint, bad url: {$e->getMessage()}";
        } catch (InvalidArgumentException $e) {
            return "Cannot access api endpoint, bad url: {$e->getMessage()}";
        }

        return '';
    }
}
