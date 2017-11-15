<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Alaress\Web\Controller\Base;


class Controller extends Base
{
    /**
     * Load $templateName
     */
    public function postUpdate()
    {
        try {
            $pd = Mapper::getInstance()->findById(1);
            new \Alaress_Probe($this->getRequest()->post, $pd);
        }
        catch (\Schoolbox\Mapper\Exception $e) {
            var_dump($e->getMessage());
        }

        die();
    }
}
