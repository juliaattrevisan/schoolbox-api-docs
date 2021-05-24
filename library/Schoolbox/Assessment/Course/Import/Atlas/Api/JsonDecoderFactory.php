<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Karriere\JsonDecoder\JsonDecoder;

/**
 * Factory class for the JSON decoder for Atlas curriculum objects.
 */
class JsonDecoderFactory
{
    /**
     * Build a JSON decoder for Atlas curriculum objects.
     *
     * @return JsonDecoder
     */
    public function build(): JsonDecoder
    {
        $decoder = new JsonDecoder(true);
        $decoder->register(new CurriculumTransformer());
        $decoder->register(new TeacherTransformer());
        $decoder->register(new UnitTransformer());
        $decoder->register(new AssessmentTransformer());
        $decoder->register(new StandardTransformer(
            new StandardParser\Acara()
        ));
        $decoder->register(new AttachmentTransformer());
        return $decoder;
    }
}
