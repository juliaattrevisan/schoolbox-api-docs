<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed\Translator;

use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Learning\Badge\Award\Translator\ModelToArray as BadgeAwardTranslator;
use Schoolbox\Learning\Moment\Feed\Cursor;

/**
 * Class ModelToArray
 */
class ModelToArray
{
    /**
     * Model-to-array translator for evidence objects.
     */
    private Composite $evidenceTranslator;


    /**
     * Model-to-array translator for badge award objects.
     */
    private BadgeAwardTranslator $badgeAwardTranslator;


    /**
     * Constructor
     *
     * @param Composite $evidenceTranslator
     * @param BadgeAwardTranslator $badgeAwardTranslator
     */
    public function __construct(
        Composite $evidenceTranslator,
        BadgeAwardTranslator $badgeAwardTranslator
    ) {
        $this->evidenceTranslator = $evidenceTranslator;
        $this->badgeAwardTranslator = $badgeAwardTranslator;
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

            default:
                break;
        }
        return $return;
    }
}
