<?php
/**
 * Created by PhpStorm.
 * User: matt.schembri
 * Date: 16/08/18
 * Time: 1:16 PM
 */

namespace Custom\Dcc\PTO;
use Schoolbox\Error\Mapper as ErrorMapper;


class Loader extends \Schoolbox\PTO\Loader
{
    /**
     * @return null|string
     */
    protected function getPTOLogin()
    {
        //Map usernames to PTOId
        $username = $this->getUser()->getUsername();
        $mapFile = '/home/dcc/PTO/parent_map.csv';

        if(!file_exists($mapFile) || !is_readable($mapFile)) {
            ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_OR_URL);
        }

        $PTOId = null;

        if (($handle = fopen($mapFile, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if ($row[0] === $username) {
                    $PTOId = $row[1];
                    break;
                }
            }
            fclose($handle);
        }

        return $PTOId;
    }

}