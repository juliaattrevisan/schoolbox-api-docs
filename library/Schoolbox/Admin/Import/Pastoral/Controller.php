<?php
/**
 * Pastoral import page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Import\Pastoral;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\News\TopicMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Pastoral\Severity\Mapper as SeverityMapper;
use Schoolbox\Pastoral\Type\Mapper as TypeMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }

    public function postImport()
    {
        $pastoralExternal = new \External_Pastoral();
        $results = $pastoralExternal->importPastoral();

        $this->getResponse()->content->set(
            array(
                'template' => '@adminImportPastoral/importResults.twig',
                'data'     => array(
                    'pageCurrent' => 'import-pastoral',
                    'imported' => $results['imported'],
                    'skipped' => $results['skipped']
                ),
            )
        );
    }

    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminImportPastoral/index.twig',
                'data'     => array(
                    'pageCurrent' => 'import-pastoral',
                ),
            )
        );
    }
}
