<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Timetable;

use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Import\UserAccount;
use Schoolbox\IConfigLoader;
use Schoolbox\Import\ImporterFactory;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Import\Timetable
 */
class Controller extends UserAccount\Controller
{

    /**
     * @var string
     */
    protected $basename = 'adminImportTimetable';


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param ImporterFactory $importerFactory
     * @param IConfigLoader   $configLoader
     */
    public function __construct(ImporterFactory $importerFactory, IConfigLoader $configLoader)
    {
        parent::__construct($importerFactory, $configLoader);

        $this->navigation = new SchoolData();
    }
}
