<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

/**
 * Class Explorer
 *
 * Intentionally fully self-contained construct that allows you to dump the contents
 * of a paginated payload from a sentral endpoint. The sentral api docs leave a lot
 * to be desired so sometimes the only way to get an accurate answer is to actually
 * get an accurate answer, and not refer to the docs
 *
 * The probe now gets individual scrolls so you can manually join data cross multiple
 * payloads of varying lengths and makes this whole job a lot easier to visualise
 *
 * Example usage: test.php
 *

use \Schoolbox\External\Connector\Sentral\Base\Endpoint;

$explorer = new Endpoint\Explorer();

$data1 = $explorer->explore('enrolments/staff?include=person,qualifications,employments');
$data2 = $explorer->explore('enrolments/person/17');

new \Alaress_Probe($data1, $data2);
die();

 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Explorer
{

    /**
     * @var Api
     */
    private $api;


    /**
     * Explorer constructor.
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function __construct()
    {

        /* Intentionally fully self-contained
         */
        $config = new \Schoolbox\Config\Loader();

        $host = $config->get('schooldata_import_endpoint_host');
        $key = $config->get('schooldata_import_endpoint_key');

        $this->api = new Api($host, $key);
    }


    /**
     * @param string $nextUrl
     * @return array
     * @throws \InvalidArgumentException
     */
    public function explore(string $nextUrl): array
    {
        $payloads = [];

        for ($i = 0; $nextUrl && $i < 100; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            $payloads[] = $payload;

            $nextUrl = $payload['links']['next'] ?? null;
        }

        return array_values($payloads);
    }
}
