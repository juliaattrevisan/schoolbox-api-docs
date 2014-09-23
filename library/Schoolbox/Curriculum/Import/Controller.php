<?php
/**
 * Controller for curriculum importing.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Import;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    public function browse()
    {
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum Import',
                'template' => '@curriculumImport/browse.twig',
                'data'     => array(),
            )
        );
    }
}
