<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Reporting\Decorator;

use Schoolbox\GenericCollection;

trait ReportingTrait
{
    /**
     * This is used to decide whether to show any supporting data at all.
     *
     * @var bool|null
     */
    private ?bool $supportingDataEnable = null;


    /**
     * A string corresponding to one of the date range options in the ui
     *
     * @var string|null
     */
    private ?string $supportingDataRange = null;


    /**
     * These are used to constrain the assessments being fetched for the mega markbook. To minimise hydration people
     * should specify all the nodes that would be needed to perform the reporting activity's feedback.
     *
     * As per tradition, zero curriculum nodes selected == all curriculum nodes selected
     *
     * @var GenericCollection|null
     */
    private ?GenericCollection $supportingDataCurriculumNodes = null;


    /**
     * These are used to constrain the folders in which we'll look for assessments for the mega markbook. To minimise
     * hydration users should select the most appropriate time period for which we fetch assessments.
     *
     * We look in this time period for assessments due within that time period;
     * also, we find any published feedback within that time period to minimise hydration to determine a person's
     * Feedback footprint.
     *
     * @var string|null
     */
    private ?string $supportingDataFolders = null;


    /**
     * These are used to constrain the assessments being fetched for the mega markbook. To minimise hydration people
     * should specify all the work types that would be needed to perform the reporting activity's feedback.
     *
     * As per tradition, zero work types selected == all work types selected
     *
     * @var GenericCollection|null
     */
    private ?GenericCollection $supportingDataWorkTypes = null;


    /**
     * This decides whether the Reporting activity's feedback screen shows an aggregated markbook from students'
     * homepages. This is optional for reporting activities that are for attendance and don't require academic details
     *
     * @var bool|null
     */
    private ?bool $supportingDataMarkbookEnable = null;


    /**
     * This is used to decide if the feedbacks should have a ckeditor for people to write comments. This is optional for
     * reporting activities that have no value offering comments, such as attendance or other clerical data.
     *
     * @var bool|null
     */
    private ?bool $feedbackCommentsEnable = null;


    /**
     * @return bool|null
     */
    public function getSupportingDataEnable(): ?bool
    {
        return $this->supportingDataEnable;
    }


    /**
     * @param bool|null $supportingDataEnable
     * @return $this
     */
    public function setSupportingDataEnable(?bool $supportingDataEnable): self
    {
        $this->supportingDataEnable = $supportingDataEnable;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSupportingDataRange(): ?string
    {
        return $this->supportingDataRange;
    }


    /**
     * @param string|null $supportingDataRange
     * @return $this
     */
    public function setSupportingDataRange(?string $supportingDataRange): self
    {
        $this->supportingDataRange = $supportingDataRange;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getSupportingDataCurriculumNodes(): ?GenericCollection
    {
        return $this->supportingDataCurriculumNodes;
    }


    /**
     * @param GenericCollection|null $supportingDataCurriculumNodes
     * @return $this
     */
    public function setSupportingDataCurriculumNodes(?GenericCollection $supportingDataCurriculumNodes): self
    {
        $this->supportingDataCurriculumNodes = $supportingDataCurriculumNodes;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSupportingDataFolders(): ?string
    {
        return $this->supportingDataFolders;
    }


    /**
     * @param string|null $supportingDataFolders
     * @return $this
     */
    public function setSupportingDataFolders(?string $supportingDataFolders): self
    {
        $this->supportingDataFolders = $supportingDataFolders;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getSupportingDataWorkTypes(): ?GenericCollection
    {
        return $this->supportingDataWorkTypes;
    }


    /**
     * @param GenericCollection|null $supportingDataWorkTypes
     * @return $this
     */
    public function setSupportingDataWorkTypes(?GenericCollection $supportingDataWorkTypes): self
    {
        $this->supportingDataWorkTypes = $supportingDataWorkTypes;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getSupportingDataMarkbookEnable(): ?bool
    {
        return $this->supportingDataMarkbookEnable;
    }


    /**
     * @param bool|null $supportingDataMarkbookEnable
     * @return $this
     */
    public function setSupportingDataMarkbookEnable(?bool $supportingDataMarkbookEnable): self
    {
        $this->supportingDataMarkbookEnable = $supportingDataMarkbookEnable;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getFeedbackCommentsEnable(): ?bool
    {
        return $this->feedbackCommentsEnable;
    }


    /**
     * @param bool|null $feedbackCommentsEnable
     * @return $this
     */
    public function setFeedbackCommentsEnable(?bool $feedbackCommentsEnable): self
    {
        $this->feedbackCommentsEnable = $feedbackCommentsEnable;
        return $this;
    }
}
