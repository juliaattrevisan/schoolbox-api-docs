<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Learning\Learnosity\Cli;

use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Url\Factory;

/**
 * Learnosity Debug tool
 *
 * Quick method for calling something on Learnosity's API, with whatever
 * this instance's configured credentials are.
 *
 * Currently this supports only GET requests.
 */
class DebugTool extends CliTool\App
{
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    // -e, --endpoint: the endpoint to call on their API
    private const OPT_SHORT_ENDPOINT = 'e:';
    private const OPT_LONG_ENDPOINT  = 'endpoint:';

    // -s, --session-id: one or more session IDs to send to the endpoint
    private const OPT_SHORT_SESSION_ID = 's:';
    private const OPT_LONG_SESSION_ID  = 'session-id:';

    // -r, --reference: one or more references to send to the endpoint
    private const OPT_SHORT_REFERENCE = 'r:';
    private const OPT_LONG_REFERENCE  = 'reference:';

    // -p, --payload: a full JSON-encoded payload
    private const OPT_SHORT_PAYLOAD = 'p:';
    private const OPT_LONG_PAYLOAD  = 'payload:';

    // -j, --json: output JSON instead of var_dump
    private const OPT_SHORT_JSON = 'j';
    private const OPT_LONG_JSON  = 'json';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_ENDPOINT,
            self::OPT_SHORT_SESSION_ID,
            self::OPT_SHORT_REFERENCE,
            self::OPT_SHORT_PAYLOAD,
            self::OPT_SHORT_JSON,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ENDPOINT,
            self::OPT_LONG_SESSION_ID,
            self::OPT_LONG_REFERENCE,
            self::OPT_LONG_PAYLOAD,
            self::OPT_LONG_JSON,
        ];
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit;
        }

        $api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );
    }

    private function printHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;
        echo <<<HELP
NAME:
    Learnosity Debug Tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Debugging tool for calling an endpoint on Learnosity's API.

    Currently and for the foreseeable future, this will call only GET requests.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit.

    $yellow-e, --endpoint$white
        The endpoint to call.

        See https://reference.learnosity.com/ for the full suite of endpoints which Learnosity supports.

    $yellow\[-s,--session-id]=SESSION_ID,...$white
        If provided, set these session IDs as part of the payload to the endpoint.

        This is intended for calls to endpoints such as /sessions/scores (https://reference.learnosity.com/data-api/endpoints/sessions_endpoints#scores).

        This may be provided as either a comma-separated list, or multiple times, to export multiple records.

    $yellow\[-r, --reference]=REFERENCE,...$white
        If provided, set these references as part of the payload to the endpoint.

        This is intended for calls to endpoints such as /itembank/activities (https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#getActivities).

        This may be provided as either a comma-separated list, or multiple times, to export multiple records.

    $yellow\[-p, --payload]=PAYLOAD$white
        If provided, set this as the payload to the endpoint.

        This is intended for calls to endpoints which accept neither session IDs nor references, and should be raw JSON data.

    $yellow-j, --json$white
        If provided, output JSON: otherwise, just var_dump the response.

        JSON output may then be piped through `jq` for pretty formatting.


HELP;
    }
}
