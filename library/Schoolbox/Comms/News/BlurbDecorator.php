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
     * @var string
     */
    private $readmore;

    /**
     * @param string     $readmore
     */
    public function __construct($readmore)
    {
        $this->readmore = $readmore;
    }


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

       
        $json['readmore'] = [
            'string' => '',
            'available' => false
        ];

        // Show 'read more' link if a body exists, and 'read more' text exists,
        // Or just set readmore to available if body exists (for links from title and feature image)
        if ($model->getBody() && \strlen($this->readmore)) {
            $json['readmore'] = [
                'string' => $this->readmore,
                'available' => true
            ];
        } elseif ($model->getBody()) {
            $json['readmore']['available'] = true;
        }

        return $json;
    }
}