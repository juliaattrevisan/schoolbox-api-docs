<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Page;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Check whether a particular filter page is available at all.
 */
class AvailabilityChecker
{
    /**
     * Config: stores whether filter pages are available.
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Is this page available at all?
     *
     * @param string $page One of the `Model::PAGE_*` constants
     * @return bool
     */
    public function isAvailable(string $page): bool
    {
        switch ($page) {
            case Model::PAGE_PENDING_ASSESSMENTS:
            case Model::PAGE_ASSESSMENT_CALENDAR:
                return true;

            case Model::PAGE_GRADES:
                return (bool)$this->config->get(ConfigMapper::NAME_GRADES_ENABLED);

            case Model::PAGE_CLASS_MARK_BOOK:
                return (bool)$this->config->get(ConfigMapper::NAME_CLASS_MARKBOOK_ENABLED);

            case Model::PAGE_COURSE_MARK_BOOK:
                return (bool)$this->config->get(ConfigMapper::NAME_COURSE_MARKBOOK_ENABLED);

            case Model::PAGE_UPCOMING_ASSESSMENTS:
                return (bool)$this->config->get(ConfigMapper::NAME_UPCOMING_ASSESSMENTS_ENABLED);

            case Model::PAGE_RUBRIC_MARK_BOOK:
                return (bool)$this->config->get(ConfigMapper::NAME_RUBRIC_MARKBOOK_ENABLED);

            case Model::PAGE_BADGE_LIST:
            case Model::PAGE_AWARD_LIST:
                return (bool)$this->config->get(ConfigMapper::NAME_BADGES_ENABLED);

            case Model::PAGE_CLASS_PASTORAL:
                return $this->config->get(ConfigMapper::NAME_PASTORAL_ENABLED)
                    && $this->config->get(ConfigMapper::NAME_LMS_ENABLED);

            default:
                // There exists `Model::PAGE_LEARNING_ANALYTICS` as well:
                // I don't think the learning analytics filter is in use at all
                return false;
        }
    }

    /**
     * Is any filter page available at all?
     *
     * Currently, this is always true as the pending assessments calendar and
     * assessment calendar filters are always available. This may change at some
     * point.
     *
     * @return bool
     */
    public function isAnyFilterAvailable(): bool
    {
        $allFilters = [
            Model::PAGE_PENDING_ASSESSMENTS,
            Model::PAGE_ASSESSMENT_CALENDAR,
            Model::PAGE_GRADES,
            Model::PAGE_CLASS_MARK_BOOK,
            Model::PAGE_COURSE_MARK_BOOK,
            Model::PAGE_UPCOMING_ASSESSMENTS,
            Model::PAGE_RUBRIC_MARK_BOOK,
            Model::PAGE_BADGE_LIST,
            Model::PAGE_AWARD_LIST,
        ];
        foreach ($allFilters as $filter) {
            if ($this->isAvailable($filter)) {
                return true;
            }
        }
        return false;
    }
}
