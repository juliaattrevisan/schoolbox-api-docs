<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Factory
{
    /**
     * Each genus of learnosity question type needs its own special attention
     *
     * @param string $questionType
     * @return IStrategy|null
     */
    public function produce(string $questionType): ?IStrategy {
        return match ($questionType) {
            /* Multiple Choice
             */
            'mcq' => new Mcq(),
            'choicematrix' => new Choicematrix(),
            /* Fill in the blanks (cloze)
             */
            'clozeassociation' => new Clozeassociation(),
            'clozedropdown' => new Clozeassociation(),
            'clozetext' => new Clozetext(),
            'imageclozeassociationV2' => new ImageclozeassociationV2(),
            'imageclozedropdown' => new Imageclozedropdown(),
            'imageclozetext' => new Imageclozetext(),
            /* Written and Recorded
             */
            'shorttext' => new Shorttext(),
            default => null,
        };
    }
}
