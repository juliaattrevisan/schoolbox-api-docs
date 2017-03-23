<?php
/**
 * Admin external database health check and settings pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\External;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Index
     */
    public function getIndex()
    {
        // Grab a connection to the external database

        /** @var IConnector $databaseExternal */
        $databaseExternal = $this->getDi()->get('databaseExternal');

        // This is just a stupid test to check if DI is working

        $rhodesFamily = $databaseExternal->executeQueryIterator(
            "SELECT * FROM [dbo].[Community] WHERE [Surname] = 'Rhodes'"
        );

        $user = new \Schoolbox\External\Connector\Synergetic\Base\User();

        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminExternal/index.twig',
            'data' => [
                'rhodesFamily' => $rhodesFamily
            ],
        ]);
    }
}
