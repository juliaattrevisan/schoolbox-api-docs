<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Class Factory
 */
class Factory
{
    /**
     * Port of `\Schoolbox\Assessment\Rubric\Factory::buildFromPostData`,
     * but builds a `Grid` object instead.
     *
     * @param Grid  $grid     A grid (which already contains an assessment and rowset)
     * @param array $postData
     * @return Grid
     */
    public function buildFromPostData(Grid $grid, array $postData): Grid
    {
        // we're only going to take the first one... this could be converted to foreach easily enough
        $rubricData = reset($postData);
//        $rubricId   = key($postData);

        $levels      = $rubricData['level']      ?: [];
        $groups      = $rubricData['group']      ?: [];
        $criteria    = $rubricData['criterion']  ?: [];
        $descriptors = $rubricData['descriptor'] ?: [];

//        $rubric = $this->spawn($rubricId);

        /** @var Level[] $levelCache */
        $levelCache = [];

        // cache levels and ensure all exist in the rubric
        foreach ($levels as $levelSequence => $level) {
            $levelName = $level['name'];
            $levelId   = $level['id'] ?? null;

            $levelCache[$levelSequence] = (new Level())
                ->setId($levelId)
                ->setName($levelName);

//            $rubric->putLevel($levelCache[$levelSequence]);
            $grid->getLevels()->add($levelCache[$levelSequence]);
        }

        /** @var Group[] $groupCache */
        $groupCache = [ 0 => new Group(null, '', null) ];

        // cache groups
        foreach ($groups as $groupId => $groupData) {
            $groupName     = $groupData['name'];
            $groupMaxValue = isset($groupData['maxValue']) ? (float)$groupData['maxValue'] : null;
            $groupCache[$groupId] = new Group($groupId, $groupName, $groupMaxValue);
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
}
