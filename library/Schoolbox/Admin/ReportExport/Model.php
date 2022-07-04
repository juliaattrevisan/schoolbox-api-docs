<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * Report Options has this structure
     */
    public const DEFAULT_OPTIONS = [
        'terms' => [],
        'yearLevels' => [],
        'campuses' => [],
        'students' => [],
        'summary' => [
            'active' => 0,
            'heading' => '',
            'workType' => [],
            'overallGrade' => 0,
            'comments' => 0,
            'rubrics' => 0
        ],
        'class' => [
            'active' => 0,
            'workType' => [],
            'overallGrade' => 0,
            'learningLevels' => 0,
            'cohort' => 0,
            'capabilityGrades' => 0,
            'progression' => 0,
            'comments' => 0,
            'rubrics' => 0
        ]
    ];

    private ?string $title = null;
    private ?string $introduction = null;
    private ?\Schoolbox\Resource\Storage\Model $logo = null;
    private ?string $options = null;
    private ?\Schoolbox\User $updatedBy = null;


    /**
     * Take the report settings and convert it into an associative array
     * so you can inspect its guts
     *
     * @return array|null
     * @throws \JsonException
     */
    public function decode(): ?array
    {
        $optionsPayload = json_decode($this->getOptions(), true, 512, JSON_THROW_ON_ERROR);

        /* Bad data or couldn't decode, for some obscure reason
         * Kind of common with third parties, more so than us
         */
        if (!$optionsPayload) {
            return null;
        }

        /* If possible, explode the csv values
        */
        $optionsPayload['terms'] = explode(',', (string)$optionsPayload['terms']);
        $optionsPayload['yearLevels'] = explode(',', (string)$optionsPayload['yearLevels']);
        $optionsPayload['campuses'] = explode(',', (string)$optionsPayload['campuses']);
        $optionsPayload['students'] = explode(',', (string)$optionsPayload['students']);

        return $optionsPayload;
    }


    /**
     * Convert the payload settings to a single serialized querystring
     *
     * @return string
     * @throws \JsonException
     */
    public function buildQuerystring(): string
    {
        $optionsPayload = json_decode($this->getOptions(), true, 512, JSON_THROW_ON_ERROR);

        /* Bad data or couldn't decode, for some obscure reason
         * Kind of common with third parties, more so than us
         */
        if (!$optionsPayload) {
            return '';
        }

        /* Recursively serialize with prefix
         * Example $stack shown below
         *
                Array
                (
                    [0] => terms=5,1,2,3,4
                    [1] => yearLevels=1,2,3
                    [2] => campuses=1
                    [3] => students=45,6,25
                    [4] => summaryActive=1
                    [5] => summaryHeading=werI am a cat
                    [6] => summaryWorkType=1,2,3,5,6,7,8,9,10,11
                    [7] => summaryOverallGrade=1
                    [8] => summaryComments=1
                    [9] => summaryRubrics=1
                    [10] => classActive=0
                    [11] => classWorkType=1,2,3,5,6,7,8,9,10,11,12
                    [12] => classOverallGrade=1
                    [13] => classLearningLevels=1
                    [14] => classCohort=1
                    [15] => classCapabilityGrades=1
                    [16] => classProgression=1
                    [17] => classComments=1
                    [18] => classRubrics=1
                )
         */
        $stack  = [];

        foreach ($optionsPayload as $key => $sectionValue) {
            if (!is_array($sectionValue)) {
                /* Non-hierarchical
                 */
                $stack[] = "{$key}={$sectionValue}";
            } else {
                /* Summary and Class subsections
                 */
                foreach ($sectionValue as $subsectionKey => $subsectionValue) {
                    $stack[] = sprintf(
                        '%s%s=%s',
                        $key,
                        ucwords($subsectionKey),
                        is_array($subsectionValue) ? implode(',', $subsectionValue) : $subsectionValue
                    );
                }
            }
        }

        return implode('&', $stack);
    }


    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getIntroduction(): ?string
    {
        return $this->introduction;
    }


    /**
     * @param string|null $introduction
     * @return $this
     */
    public function setIntroduction(?string $introduction): self
    {
        $this->introduction = $introduction;
        return $this;
    }


    /**
     * @return \Schoolbox\Resource\Storage\Model|null
     */
    public function getLogo(): ?\Schoolbox\Resource\Storage\Model
    {
        return $this->logo;
    }


    /**
     * @param \Schoolbox\Resource\Storage\Model|null $logo
     * @return $this
     */
    public function setLogo(?\Schoolbox\Resource\Storage\Model $logo): self
    {
        $this->logo = $logo;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getOptions(): ?string
    {
        return $this->options;
    }


    /**
     * @param string|null $options
     * @return $this
     */
    public function setOptions(?string $options): self
    {
        $this->options = $options;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }


    /**
     * @param User|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
