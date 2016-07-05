<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\School;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\TagCloud\Mapper as TagCloudMapper;

class Controller extends Base
{
    /**
     * @var TagCloudMapper
     */
    protected $schoolTagCloudMapper;

    public function __construct()
    {
        $this->setSchoolTagCloudMapper(TagCloudMapper::getInstance('sportsfixtures_school', 'id', 'name'));
    }

    /**
     * List of external schools
     */
    public function getList()
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@sportsfixturesSchool/list.twig', [
                'schools' => $this->getSchoolTagCloudMapper()->findAll()
            ])
        );
    }

    public function postAddSchool() {
        $this->getSchoolTagCloudMapper()->getTagId($this->getRequest()->post["name"]);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSchool.getList', []));
    }

    /**
     * @return TagCloudMapper
     */
    protected function getSchoolTagCloudMapper()
    {
        return $this->schoolTagCloudMapper;
    }

    /**
     * @param TagCloudMapper $schoolTagCloudMapper
     */
    protected function setSchoolTagCloudMapper($schoolTagCloudMapper)
    {
        $this->schoolTagCloudMapper = $schoolTagCloudMapper;
    }
}
