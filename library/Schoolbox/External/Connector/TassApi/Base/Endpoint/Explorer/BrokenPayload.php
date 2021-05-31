<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer;

/**
 * Class BrokenPayload
 *
 * Using an encoded payload string, offer diagnostics as to what it contained
 * and a chance to replay to see if it consistently fails

<?php
require_once __DIR__ . '/../conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$config = new \Schoolbox\Config\Loader();

$host = $config->get('schooldata_import_endpoint_host');
$dsn = $config->get('schooldata_import_endpoint_key');

use Schoolbox\External\Connector\TassApi\Base\Endpoint;

$api = [
    Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION),
    Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS),
    Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT)
];

(new Endpoint\Explorer\BrokenPayload($api))->explore($_POST);

die();

 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer
 */
class BrokenPayload
{

    /**
     * @var array
     */
    private array $api;


    /**
     * BrokenPayload constructor.
     *
     * @param array $api
     */
    public function __construct(array $api)
    {
        $this->api = $api;
    }


    /**
     * @param array $postData
     * @throws \DebugBar\DebugBarException
     * @throws \JsonException
     */
    public function explore(array $postData): void
    {
        echo <<<DUANE
<!doctype html>
<html>
<head></head>
<body>
    <h1>Replay API request that inexplicably died</h1>
    <form action="#" method="post">
    
        <label>Encoded API Payload</label>
        <input type="text" name="brokenPayload" style="width: 90%; padding: 0.5rem; display: block; clear: both" />
        
        <input type="submit" value="Replay this api request" style="width: 20%; padding: 0.5rem; margin: 0.5rem 0;" />
    </form>
DUANE;

        if (isset($postData['brokenPayload'])) {
            $brokenPayload = (new BrokenPayloadCodec())->decode($postData['brokenPayload']);

            echo '<hr /><h2>this payload died</h2>';
            new \Alaress_Probe($brokenPayload);
            echo '<hr />';

            echo sprintf(
                '<h2>replaying access to %s %s</h2>',
                $brokenPayload['module'],
                $brokenPayload['method'],
            );

            try {
                $result = $this->api[$brokenPayload['module']]->retrieveDocument($brokenPayload['method'], $brokenPayload['params']);
            } catch (\Exception $e) {
                new \Alaress_Probe($e->getMessage());
            }
        }

        echo <<<COX
</body>
</html>
COX;
    }
}
