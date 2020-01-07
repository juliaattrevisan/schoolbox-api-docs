<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile\Receiver;
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
        $expectedXmls = $this->findSpecimens();

        $numFound = \count(array_filter(array_values($expectedXmls)));

        return $numFound === \count($expectedXmls);
    }


    /**
     * Find a specimen xml for each fo the $expectedXmls
     *
     * @return array
     */
    private function findSpecimens(): array
    {
        $expectedXmls = [
            Receiver::XML_FULL => false,
            Receiver::XML_STUDENTTIMETABLES => false
        ];

        $dirhandle = opendir($this->path);

        while ($xmlFile = readdir($dirhandle)) {
            if ($xmlFile !== '.' && $xmlFile !== '..') {
                $path = "{$this->path}/{$xmlFile}";

                /* There's at least one profile xml and one student timetables xml
                 * and they are both readable
                 */
                if ((new Api($path))->isXmlFileExtant()) {
                    foreach ($expectedXmls as $key => $value) {
                        if (strpos($xmlFile, $key) !== false) {
                            $expectedXmls[$key] = $path;
                        }
                    }
                }
            }
        }

        return $expectedXmls;
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getReport(): string
    {
        $expectedXmls = $this->findSpecimens();

        $specifications = [
            new Profile\Specification(null, new Batchxml(new Api($expectedXmls[Receiver::XML_FULL]))),
            new Timetable\Specification(null, new Batchxml(new Api($expectedXmls[Receiver::XML_STUDENTTIMETABLES]))),
        ];

        $result = [];

        foreach ($specifications as $specification) {
            try {
                $result[] = implode("\n", $specification->check());
            } catch (\RuntimeException $e) {
                $result[] = "Cannot access api endpoint: {$e->getMessage()}";
            }
        }

        return implode("\n", $result);
    }
}
