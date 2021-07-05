<?php

/**
 * Wrapper for a PHPLib template object and a mailer class.
 */

class Mailer extends \Alaress\Mail\AbstractMailer
{

    /**
     * Template object which is used to render the page
     *
     * @var Template
     */
    protected $template;

    /**
     * Template label
     *
     * @var string
     */
    public $t_label = 'tplBODY';

    /**
     * Mailer constructor.
     * @param        $template
     * @param string $subject
     * @param string $to
     * @param string $host
     */
    public function __construct($template, $subject = "", $to = "", $host = '')
    {

        parent::__construct($template, $subject, $to, $host);

        //setup HTML template
        $this->template = new Template(SITE_root . '/templates');
        $this->loadTemplate($template);

        //setup defaults
        if ($host) {
            $this->template->set_var('SITE_DOMAIN', $host);
        } else {
            $this->template->set_var('SITE_DOMAIN', $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
        }
    }

    /**
     * @param $file
     */
    public function loadTemplate($file)
    {
        $this->template->set_file($this->t_label, $this->getTemplate($file));
    }

    /**
     * @param $template
     * @return string
     */
    public function getTemplate($template)
    {
        $root = defined('MAIL_templates') ? MAIL_templates : SITE_root . '/templates';

        if (is_file($root . '/' . Config::get('site_school_code') . '/email/' . $template)) {
            return Config::get('site_school_code') . '/email/' . $template;
        } else {
            return 'core/email/' . $template;
        }
    }

    /**
     * @param        $name
     * @param string $value
     */
    public function setVar($name, $value = '')
    {
        $this->template->set_var($name, $value);
    }

    /**
     * @param $parent
     * @param $handle
     * @param $name
     */
    public function setBlock($parent, $handle, $name)
    {
        $this->template->set_block($parent, $handle, $name);
    }

    /**
     * @param       $target
     * @param       $handle
     * @param false $append
     */
    public function parse($target, $handle, $append = false)
    {
        $this->template->parse($target, $handle, $append);
    }

    /**
     * @return bool|string|null
     */
    public function composeHTML()
    {
        //initialise the email document
        return $this->template->parse("final", $this->t_label);
    }

    /**
     * @return string
     */
    public function composePlain()
    {
        $converter = new Alaress\Html\ToText($this->composeHTML());
        return $converter->getText();
    }

    public function printEmail()
    {
        print $this->composeHTML();
    }

    /**
     * Converts a template path in PHPLib format to one that works as the
     * template path for this class.
     *
     * Gets around the issue with mail templates always being expected to be in
     * "templates/{$site_school_code,core}/email".
     *
     * I don't want it to be static either, but it has to be as I need to call
     * this method before the mailer object gets created.
     *
     * @param string $template  A path to a PHPLib template
     *                          Example: "modules/leave/someEmail.tpl" will
     *                          check "templates/$site_school_code/" for this
     *                          template first, then "templates/core"
     * @return string
     */
    public static function translateTemplatePathToMailPath($template)
    {
        $schoolTemplate  = strtolower(Config::get('site_school_code')) . '/' . $template;
        $genericTemplate = 'core/' . $template;
        if (file_exists(SITE_root . '/templates/' . $schoolTemplate)) {
            return '../../' . $schoolTemplate;
        }
        return '../../' . $genericTemplate;
    }
}
