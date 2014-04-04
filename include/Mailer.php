<?php
/**
 * Wrapper for a PHPLib template object and a mailer class.
 */

class Mailer extends \Alaress\Mail\AbstractMailer {

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

    function __construct($template, $subject = "", $to = "", $host = '') {

        parent::__construct($template, $subject, $to, $host);

        //setup HTML template
        $this->template = new Template( Config::get('template_dir') );
        $this->loadTemplate($template);

        //setup defaults
        if($host) {
            $this->template->set_var('SITE_DOMAIN', $host);
        }
        else {
            $this->template->set_var('SITE_DOMAIN', $_SERVER['HTTP_HOST']);
        }
    }


    function loadTemplate ($file) {
        $this->template->set_file($this->t_label, $this->getTemplate($file));
    }

    function getTemplate ($template) {
        $root = defined('MAIL_templates') ? MAIL_templates : SITE_root . '/templates';

        if ( is_file($root . '/' . Cfg::get('site_location') . '/email/' . $template) ) {
            return Cfg::get('site_location') . '/email/' . $template;
        }
        else {
            return 'core/email/' . $template;
        }

    }

    function setVar ($name, $value = '') {
        $this->template->set_var($name, $value);
    }

    function setBlock ($parent, $handle, $name) {
        $this->template->set_block($parent, $handle, $name);
    }

    function parse ($target, $handle, $append = false) {
        $this->template->parse($target, $handle, $append);
    }

    function composeHTML () {
        //initialise the email document
        return $this->template->parse("final", $this->t_label);
    }

	function composePlain() {
		$converter = new \Html2Text\Html2Text($this->composeHTML());
		return $converter->get_text();
	}

    function printEmail () {
        print $this->composeHTML();
    }

}
