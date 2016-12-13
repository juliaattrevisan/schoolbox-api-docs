<?php
/**
 * Lists admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Usage;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use IMSGlobal\LTI\ToolProvider as LTI;
use IMSGlobal\LTI\ToolProvider\Service as LTIService;
use Schoolbox\LTI\Provider\Model as Provider;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;

class Controller extends Base
{
    /**
     *
     */
    public function getGlobal()
    {
        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminUsage/global.twig',
            'data'     => [
                'pageCurrent' => 'usage',
            ],
        ]);
    }

    /**
     *
     */
    public function getUser()
    {
        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@adminUsage/user.twig',
            'data'     => [
                'pageCurrent' => 'usage',
            ],
        ]);
    }
}
