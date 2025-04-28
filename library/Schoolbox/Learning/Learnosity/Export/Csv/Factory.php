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
    public function produce(string $questionType): ?IStrategy
    {
        return match ($questionType) {
            /* Multiple Choice
             */
            'mcq' => new Mcq(),
            'choicematrix' => new Choicematrix(),
            /* Fill in the blanks (cloze)
             * Note that clozeassociation and clozedropdown happen to behave synonymously
             */
            'clozeassociation' => new Clozeassociation(),
            'clozedropdown' => new Clozeassociation(),
            'clozetext' => new Clozetext(),
            'imageclozeassociationV2' => new ImageclozeassociationV2(),
            'imageclozedropdown' => new Imageclozedropdown(),
            'imageclozetext' => new Imageclozetext(),
            /* Classify, Match, Order
             * Note that orderlist and sortlist happen to behave synonymously
             */
            'classification' => new Classification(),
            'association' => new Association(),
            'orderlist' => new Orderlist(),
            'sortlist' => new Orderlist(),
            /* Written and Recorded
             * We aren't going to render audio/video media in a csv
             */
            'longtextV2' => new LongtextV2(),
            'plaintext' => new Plaintext(),
            'shorttext' => new Shorttext(),
            'audio' => new NotApplicable(),
            'video' => new NotApplicable(),
            /* Highlight and Drawing
             */
            'hotspot' => new NotApplicable(),
            'tokenhighlight' => new Tokenhighlight(),
            'simpleshading' => new Simpleshading(),
            'drawing' => new NotApplicable(),
            /* Graphing
             * It's possible to encode graphplotting into serialized data but it'll be so weirdly
             * convoluted that nobody but us would figure out how to really take advantage of it.
             * It'll be too hard for people to comprehend any encoding scheme we devise here.
             */
            'graphplotting' => new NotApplicable(),
            'numberline' => new Numberline(),
            'numberlineplot' => new Numberlineplot(),
            /* Math
             * I couldn't figure out how to get Math Question Generator to spawn, too many weird ui bugs
             * Anyway, disregard all of these, because that's way too much hankypanky. Maybe one day we
             * will figure out a sane/meaningful way to render this for people.
             */
            'clozeformulaV2' => new NotApplicable(),
            'imageclozeformulaV2' => new NotApplicable(),
            'clozeformula' => new NotApplicable(),
            'imageclozeformula' => new NotApplicable(),
            'formulaessayV2' => new NotApplicable(),
            /* Charts
             */
            'simplechart' => new Simplechart(),
            /* Chemistry
             * These are all formula editors -_-
             */
            'imageclozechemistry' => new NotApplicable(),
            'chemistryessayV2' => new NotApplicable(),
            'clozechemistry' => new NotApplicable(),
            'chemistry' => new NotApplicable(),
            /* Unknown thing encountered; might be:
             *     a new feature, or
             *     Math Question Generator, or
             *     Image Annotation Upload
             */
            default => null,
        };
    }
}
