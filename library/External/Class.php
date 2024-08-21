<?php

/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */

use Alaress\Redis;
use External\CachingConnectorAbstract;
use Schoolbox\Config\Loader;

class External_Class extends CachingConnectorAbstract implements External_ClassInterface
{
    /**
     * Constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct(
            (new External_DatabaseFactory())->buildClassConnector(Loader::getInstance()),
            new External_Offline_Class(),
            Redis::getCacheInstance(),
            Config::get('external_cache_timeout')
        );
    }

    public function getStudents(string $classCode): array
    {
        $result = $this->getCachedResult(__FUNCTION__, func_get_args());
        /* Sort by lastname then by firstname
         */
        if (is_array($result)) {
            usort(
                $result,
                function ($a, $b) {
                    $last = strnatcasecmp($a['lastname'] ?? '', $b['lastname'] ?? '');
                    if ($last == 0) {
                        return strnatcasecmp($a['firstname'], $b['firstname']);
                    }
                    return $last;
                }
            );
            return $result;
        }
        throw new UnexpectedValueException(
            sprintf('Cached result is somehow not an array: received %s', gettype($result)),
        );
    }

    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }

    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }
}
