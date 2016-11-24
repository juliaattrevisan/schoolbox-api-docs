<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\School;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\TagCloud\Mapper as TagCloudMapper;
use \Schoolbox\TagCloud\Model as TagCloud;

class Controller extends Base
{
    /**
     * @var TagCloudMapper
     */
    protected $schoolTagCloudMapper;

    /**
     * Controller constructor.
     */
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

    /**
     * Add external school to tag cloud
     */
    public function postAddSchool() {
        $entityId = $this->getSchoolTagCloudMapper()->getTagId($this->getRequest()->post["name"]);

        if ($this->getRequest()->isXhr()) {
            $status = [
                'id' => $entityId,
                'update' => $this->getRouter()->generate('sportsfixturesSchool.postEditSchool', [
                    'entityId' => $entityId
                ]),
                'delete' => $this->getRouter()->generate('sportsfixturesSchool.postDeleteSchool', [
                    'entityId' => $entityId
                ])
            ];

            $this->getResponse()->content->set($this->buildJsonOutput($status));
            return;
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSchool.getList', []));
    }

    /**
     * Update name of school
     * @param $entityId int
     */
    public function postEditSchool($entityId) {

        /** @var TagCloud $tag */
        $tag = $this->getSchoolTagCloudMapper()->findById($entityId);
        $tag->setUpdatedAt(\Alaress_Date_Format::now());
        $tag->setName($this->getRequest()->post["name"]);
        $this->getSchoolTagCloudMapper()->save($tag);

        if ($this->getRequest()->isXhr()) {
            $status = [
                'id' => $entityId,
                'update' => $this->getRouter()->generate('sportsfixturesSchool.postEditSchool', [
                    'entityId' => $entityId
                ]),
                'delete' => $this->getRouter()->generate('sportsfixturesSchool.postDeleteSchool', [
                    'entityId' => $entityId
                ])
            ];

            $this->getResponse()->content->set($this->buildJsonOutput($status));
            return;
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSchool.getList', []));
    }

    /**
     * Delete external school from tag cloud
     * @param $entityId int
     */
    public function postDeleteSchool($entityId) {
        /** @var TagCloud $tag */
        $tag = $this->getSchoolTagCloudMapper()->findById($entityId);
        $this->getSchoolTagCloudMapper()->delete($tag);
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
