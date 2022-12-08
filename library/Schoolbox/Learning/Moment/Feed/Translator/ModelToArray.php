<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed\Translator;

use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Learning\Badge\Award\Translator\ModelToArray as BadgeAwardTranslator;
use Schoolbox\Learning\Moment\Feed\Cursor;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Translator\ModelToArray as DueWorkFeedbackTranslator;

/**
 * Class ModelToArray
 */
class ModelToArray
{
    /**
     * Constructor
     *
     * @param Composite                 $evidenceTranslator
     * @param BadgeAwardTranslator      $badgeAwardTranslator
     * @param DueWorkFeedbackTranslator $dueWorkFeedbackTranslator
     */
    public function __construct(
        private Composite $evidenceTranslator,
        private BadgeAwardTranslator $badgeAwardTranslator,
        private DueWorkFeedbackTranslator $dueWorkFeedbackTranslator
    ) {
    }


    /**
     * @param array $result
     * @return array
     */
    public function __invoke(array $result): array
    {
        $discriminator = $result['discriminator'] ?? null;
        $return        = ['discriminator' => $discriminator];

        // Set the object and its date of creation into the return
        switch ($result['discriminator'] ?? null) {
            case Cursor::DISCRIMINATOR_EVIDENCE:
                $return['object'] = $this->evidenceTranslator->__invoke($result['object']);
                $return['date']   = $return['object']['createdAt'];
                break;

            case Cursor::DISCRIMINATOR_BADGE_AWARD:
                $return['object'] = $this->badgeAwardTranslator->__invoke($result['object']);
                $return['date']   = $return['object']['awardedAt'];
                break;

            case Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK:
                $return['object'] = $this->dueWorkFeedbackTranslator->__invoke($result['object']);
                $return['date']   = $return['object']['publishedAt'];
                break;

            default:
                break;
        }
        return $return;
    }
}
