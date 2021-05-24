<?php

/**
 * Output handling class that renders a Twig template and data into a theme.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Output;

use Alaress_Date_Format;
use Alaress\Web\Output\DataGenerator\IDataGenerator;

class ThemedHtml extends Html
{
    /**
     * The tab that is selected for this response.
     *
     * If not set via `setTab`, it uses the version from the response.
     *
     * @var string
     */
    protected $tab = null;

    /**
     * The value of the `<title>` tag of this response.
     *
     * If not set via `setTitle`, it uses the version from the response.
     *
     * @var string
     */
    protected $title = null;


    /**
     * @var IDataGenerator[]
     */
    private $dataGenerators = [];


    /**
     * Adds a data generator to this object.
     *
     * The result of `$generator->generate()` will be available in the template
     * in key `$key`
     *
     * @param string         $key
     * @param IDataGenerator $generator
     * @return ThemedHtml
     */
    public function addDataGenerator(string $key, IDataGenerator $generator): self
    {
        $this->dataGenerators[$key] = $generator;
        return $this;
    }


    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with "template" and "data"
     * keys.
     */
    protected function outputResponseBody()
    {
        $template = $this->getTemplate();
        $data     = [];
        if (empty($template)) {
            $code = $this->getResponse()->status->getCode();
            if ($code === 404) {
                $template = 'common/error/404.twig';
            } else {
                $template = 'common/error/general.twig';
                $data     = [
                    'date'    => Alaress_Date_Format::now(),
                    'summary' => [
                        'message' => 'Template not found',
                    ],
                ];
            }
        } else {
            $data = $this->getData();
        }

        $chromeData = [
            'tab'   => $this->getTab(),
            'title' => $this->getTitle(),
        ];
        $data       = [
            'template' => $template,
            'data'     => $data,
            'chrome'   => $chromeData,
        ];
        foreach ($this->dataGenerators as $key => $generator) {
            $data[$key] = $generator->generate($chromeData);
        }

        // Render it all out
        echo $this->templateEngine->render('include/chrome.twig', $data);
    }

    /**
     * @return string
     */
    public function getTab()
    {
        return $this->tab ?? $this->retrieveFieldFromResponse('tab');
    }

    /**
     * @param  string $tab
     * @return $this
     */
    public function setTab($tab)
    {
        $this->tab = $tab;
        return $this;
    }

    /**
     * @return string
     */
    protected function getTitle()
    {
        return $this->title ?? $this->retrieveFieldFromResponse('title');
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }
}
