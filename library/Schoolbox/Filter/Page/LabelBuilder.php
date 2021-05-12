<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Page;

use Schoolbox\L10n\RosettaStone;

/**
 * Class LabelBuilder
 */
class LabelBuilder
{
    /**
     * Translator class: required for building some labels.
     */
    private RosettaStone $translator;

    /**
     * Constructor
     *
     * @param RosettaStone $translator
     */
    public function __construct(RosettaStone $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Builds a label for this page.
     *
     * @param string $page One of the `Model::PAGE_*` constants
     * @return string
     */
    public function buildLabel(string $page): string
    {
        switch ($page) {
            case Model::PAGE_PENDING_ASSESSMENTS:
                $assessmentLocalised = $this->translator->translate('assessment', true, true);
                return "Pending $assessmentLocalised";

            case Model::PAGE_ASSESSMENT_CALENDAR:
                $assessmentLocalised = $this->translator->translate('assessment', true, true);
                return "$assessmentLocalised Calendar";

            case Model::PAGE_GRADES:
                return 'Grades';

            case Model::PAGE_CLASS_MARK_BOOK:
                return 'Class Markbook';

            case Model::PAGE_COURSE_MARK_BOOK:
                return 'Course Markbook';

            case Model::PAGE_UPCOMING_ASSESSMENTS:
                // intentionally and contentiously not localised
                return "Upcoming Due Work";

            case Model::PAGE_RUBRIC_MARK_BOOK:
                return 'Rubric Markbook';

            case Model::PAGE_BADGE_LIST:
                return "Badge List";

            case Model::PAGE_LEARNING_ANALYTICS:
                // I don't think this filter is in use at all:
                // it does have a label though
                return "Learning Analytics";

            default:
                return '';
        }
    }
}
