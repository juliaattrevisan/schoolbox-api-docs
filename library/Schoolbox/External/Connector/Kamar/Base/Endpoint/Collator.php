<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

/**
 * Class Collator
 *
 * Find at least one file for each of the $expectedXmls in the $rootPath specified
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Collator
{

    /**
     * Find at least one file for each of the $expectedXmls in the $rootPath specified
     *
     * @param string $rootPath
     * @return array
     */
    public function collate(string $rootPath): array
    {
        $expectedXmls = [
            Receiver::XML_FULL => '',
            Receiver::XML_STUDENTTIMETABLES => ''
        ];

        $dirhandle = opendir($rootPath);

        while ($xmlFile = readdir($dirhandle)) {
            if ($xmlFile !== '.' && $xmlFile !== '..') {
                $path = "{$rootPath}/{$xmlFile}";

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
}
