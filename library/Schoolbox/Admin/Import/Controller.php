<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\SchoolData;

/**
 * Class Controller
 *
 * Present a landing page for the import destination you chose
 *
 * @package Schoolbox\Admin\Import
 */
class Controller extends Base
{

    /**
     * Landing Page for Direct Import
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function getDirectIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/direct/index.twig', [
            'pageCurrent' => 'import/direct',
            'allNavigation'  => (new Direct($this->getConfigLoader()))->buildNavigation($this->getRouter())->getAllNodes()
        ])->setTitle('Direct Import'));
    }


    /**
     * Landing Page for School Data Import
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function getSchoolDataIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData/index.twig', [
            'pageCurrent' => 'import/schoolData',
            'allNavigation'  => (new SchoolData())->buildNavigation($this->getRouter())->getAllNodes()
        ])->setTitle('School Data Import'));
    }
}
