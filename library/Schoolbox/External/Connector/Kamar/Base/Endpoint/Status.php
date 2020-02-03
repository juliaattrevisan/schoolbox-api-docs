<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\CliRenderer;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

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
    private $path;


    /**
     * Status constructor.
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
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
        $expectedXmls = (new Collator())->collate($this->path);

        $numFound = \count(array_filter(array_values($expectedXmls)));

        return $numFound === \count($expectedXmls);
    }


    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getReport(): array
    {
        $expectedXmls = (new Collator())->collate($this->path);

        $specifications = [
            Receiver::XML_FULL => new Profile\Specification(new Batchxml(new Api($expectedXmls[Receiver::XML_FULL]))),
            Receiver::XML_STUDENTTIMETABLES => new Timetable\Specification(new Batchxml(new Api($expectedXmls[Receiver::XML_STUDENTTIMETABLES]))),
        ];

        $result = [];

        foreach ($specifications as $key => $specification) {
            try {
                $result = array_merge($result, $specification->check());
            } catch (\RuntimeException $e) {
                $result = array_merge($result, [
                    'message' => "Cannot access api endpoint: cannot open/read at least one {$key} xml",
                    'status' => CliRenderer::STATUS_EMPTY
                ]);
            }
        }

        return $result;
    }
}
