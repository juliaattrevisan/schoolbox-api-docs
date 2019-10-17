<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Web\Controller\Base;
use Schoolbox\Import\ImportNavigation;

/**
 * Class Controller
 *
 * Present a landing page for the import destination you chose
 *
 * @package Schoolbox\Admin\Import
 */
class Controller extends Base
{

    use ImportNavigation;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Landing Page for Direct Import
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function getDirectIndex()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/direct.twig', [
            'pageCurrent' => 'import/direct',
            'allNavigation'  => $this->buildDirectNavigation($this->getRouter())->getAllNodes()
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
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminImportIndex/schoolData.twig', [
            'pageCurrent' => 'import/schoolData',
            'allNavigation'  => $this->buildSchoolDataNavigation($this->getRouter())->getAllNodes()
        ])->setTitle('School Data Import'));
    }
}
