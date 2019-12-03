<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

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
     * @param string $host
     * @param string $key
     */
    public function __construct(string $host, string $key)
    {
        $this->api = new Api($host, $key);
    }


    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable(): bool
    {
        try {
            $batchxml = new Batchxml($this->api);

            $result = $batchxml->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil');

            return true;
        } catch (\RuntimeException|InvalidUriPartException|InvalidArgumentException $e) {

            // noop
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
            return ["Cannot access api endpoint: {$e->getMessage()}"];
        } catch (InvalidUriPartException $e) {
            return ["Cannot access api endpoint, bad url: {$e->getMessage()}"];
        } catch (InvalidArgumentException $e) {
            return ["Cannot access api endpoint, bad url: {$e->getMessage()}"];
        }

        return [];
    }
}
