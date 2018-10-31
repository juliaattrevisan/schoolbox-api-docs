<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 */

namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;
use TextFormatter;
use Scrub;

class BlurbDecorator implements IDecorator
{
    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if (!$model instanceof Model) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        if (!$model->getBlurb()) {
            $json['blurb'] = TextFormatter::cutSentence(Scrub::html2text($model->getBody()), 300);
        }

        return $json;
    }
}