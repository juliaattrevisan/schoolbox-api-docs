<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Stk\OleReport;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use External_UserInterface;
use Schoolbox\Assessment\Term;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

/**
 * Module definition for SaintKentigern's custom OLE report.
 *
 * @package Custom\Stk\OleReport
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'customStkOleReport';

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class, [ ])
        );
    }
}
