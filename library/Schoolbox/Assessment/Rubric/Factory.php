<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric as OldRubric;
use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Criteria\Group;
use Schoolbox\Resource\Folder;

class Factory
{

    /** @var int */
    protected $rubricId = null;

    /** @var bool */
    protected $multiSelect = false;

    /** @var Rubric */
    protected $prototype;


    /**
     * Factory constructor.
     *
     * @param Rubric $prototype
     */
    public function __construct(Rubric $prototype = null)
    {
        $this->prototype = $prototype ?: new Rubric();
    }


    /**
     * @param int $rubricId
     * @return static
     */
    public function setRubricId($rubricId)
    {
        $this->rubricId = $rubricId;
        return $this;
    }


    /**
     * @param boolean $multiSelect
     * @return static
     */
    public function setMultiSelect($multiSelect)
    {
        $this->multiSelect = $multiSelect;
        return $this;
    }


    /**
     * Construct a Rubric object based on form datum
     *
     * @param array $formData
     * @param bool  $includeEmptyCriteria
     * @return Rubric
     */
    public function buildFromPostData(array $formData, $includeEmptyCriteria)
    {
        // we're only going to take the first one... this could be converted to foreach easily enough
        $rubricData = reset($formData);
        $rubricId   = key($formData);

        $levels      = $rubricData['level']      ?: [];
        $groups      = $rubricData['group']      ?: [];
        $criteria    = $rubricData['criterion']  ?: [];
        $descriptors = $rubricData['descriptor'] ?: [];

        $rubric = $this->spawn($rubricId);

        /** @var Level[] $levelCache */
        $levelCache = [];

        // cache levels and ensure all exist in the rubric
        foreach ($levels as $levelSequence => $level) {
            $levelName = $level['name'];
            $levelId   = isset($level['id']) ? $level['id'] : null;

            $levelCache[$levelSequence] = new Level($levelId, $levelName);

            $rubric->putLevel($levelCache[$levelSequence]);
        }

        /** @var Group[] $groupCache */
        $groupCache = [ 0 => new Group(null, '') ];

        // cache groups
        foreach ($groups as $groupId => $groupData) {
            $groupName = $groupData['name'];
            $groupCache[$groupId] = new Group($groupId, $groupName);
        }

        foreach ($criteria as $criterionId => $criterion) {

            $groupId          = $criterion['groupId'];
            $criterionEnabled = isset($criterion['on']) && $criterion['on'];
            $criterionName    = $criterion['name'];

            // spawn
            $c = new Criterion($criterionId, $criterionName);

            // set readonliness
            $c->setReadOnly(isset($criterion['readonly']) && $criterion['readonly']);

            // retrieve from cache
            $g = $groupCache[$groupId];

            // cascade readonliness into group
            if ($c->isReadOnly()) {
                $g->setReadOnly(true);
            }

            if ($includeEmptyCriteria) {
                $rubric->putCriterion($g, $c);
            }

            if (!$criterionEnabled) {
                continue;
            }

            foreach ($levelCache as $levelSequence => $l) {

                if (empty($descriptors[$criterionId][$levelSequence])) {
                    continue;
                }

                $descriptor      = $descriptors[$criterionId][$levelSequence];
                $descriptorId    = isset($descriptor['id']) ? $descriptor['id'] : null;
                $descriptorText  = $descriptor['text'];
                $descriptorValue = $descriptor['points'];

                if (strlen($descriptorText) || strlen($descriptorValue)) {
                    $d = new Descriptor($descriptorText, $descriptorValue, $descriptorId);
                    try {
                        $rubric->putDescriptor($g, $c, $l, $d);
                    } catch (InvalidArgumentException $e) {
                        echo "Unable to put descriptor";
                    }
                }

            }

        }

        return $rubric;
    }


    /**
     * Welcome to the `new`
     *
     * @param int $id
     * @param bool $multi
     * @return Rubric
     */
    protected function spawn($id = null, $multi = null)
    {
        $clone = clone $this->prototype;

        $clone->setId($id ?: $this->rubricId)
              ->setMultiSelect($multi ?: $this->multiSelect);

        return $clone;
    }

}
