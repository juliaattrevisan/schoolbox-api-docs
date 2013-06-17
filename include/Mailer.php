<?php
/**
 * Wrapper for a PHPLib template object and a mailer class.
 */

class Mailer {

    /**
     * Template object which is used to render the page
     *
     * @var Template
     */
    protected $template;

    /**
     * List of headers that are added to the email that is sent.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * The mailer object
     *
     * @var Swift_Message
     */
    protected $email;

    /**
     * Template label
     *
     * @var string
     */
    public $t_label = 'tplBODY';

    /**
     * Recipient of email
     *
     * @var string
     */
    public $to = '';

    /**
     * Instance of mail transporter.
     *
     * @var Swift_Transport
     */
    protected static $transport;

    function Mailer ($template, $subject = "", $to = "", $host = '') {

        //setup header
        $this->setFrom(Cfg::get('mail_from_address'), Cfg::get('mail_from_name'));
        $this->headers['MessageID'] = '<' . md5(mktime()) . '@lektecmailer>';

        $this->setTo($to);
        $this->setSubject($subject);

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

        $this->email = Swift_Message::newInstance();
    }

    function setFrom ($email, $name = "") {
        if ( $name ) {
            $this->headers['From'] = $name . ' <' . $email . '>';
            $this->headers['Return-Path'] = $email;
        }
        else {
            $this->headers['From'] = $email;
            $this->headers['Return-Path'] = $email;
        }
    }

    function setTo ($to) {
        if ( is_array($to) ) {

            if ( $to['email'] ) {
                if ( $to['name'] ) {
                    $this->to = $to['name'] . ' <' . $to['email'] . '>';
                }
                else {
                    $this->to = $to['email'];
                }
            }
            else {

                $this->to = '';
                foreach ( $to as $email ) {
                    if ( $email['name'] ) {
                        $this->to .= $email['name'] . ' <' . $email['email'] . '>,';
                    }
                    else {
                        $this->to .= $email['email'] . ',';
                    }
                }
                $this->to = substr($this->to, 0, -1); //remove trailing ,
            }
        }
        else {
            $this->to = $to;
        }

        $this->headers['To'] = $to;
    }

    function setSubject ($subject) {
        $this->headers['Subject'] = $subject;
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

    function addFile ($file, $type = 'text/plain', $filename = '', $isFile = true) {
        //add attachment to email
        if ($isFile) {
            $attachment = Swift_Attachment::fromPath($file, $type);
        }
        else {
            $attachment = Swift_Attachment::newInstance($file, null, $type);
        }
        if ($filename) {
            $attachment->setFilename($filename);
        }
        $this->email->attach($attachment);
    }

    function addImage ($file, $type = 'application/octet') {
        //add inline image to email
        // Returns a string so its source can be used later
        return $this->email->embed(Swift_EmbeddedFile::fromPath($file)->setContentType($type));
    }

    function composeHTML () {
        //initialise the email document
        return $this->template->parse("final", $this->t_label);
    }

    function printEmail () {
        print $this->composeHTML();
    }

    function send ($to = "") {

        if ( !empty( $to ) ) {
            $this->setTo($to);
        }

        //
        // construct body and set headers
        $body = $this->composeHTML();
        $this->email->setBody(strip_tags($body), null, 'utf-8');
        $this->email->addPart($body, 'text/html', 'utf-8');
        foreach ($this->headers as $name => $value) {
            $this->email->getHeaders()->addTextHeader($name, $value);
        }

        //
        // create transport and send email
        try {
            Swift_Mailer::newInstance(self::getSwiftTransport())->send($this->email);
        }
        catch (Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * Creates a Swift mailer transport instance, or gets last one created if it
     * has already been created.
     *
     * The exact transport used depends on configuration settings - if a SMTP
     * host and port are configured, it returns a Swift_SmtpTransport, otherwise
     * a Swift_MailTransport object.
     *
     * @return Swift_Transport
     */
    public static function getSwiftTransport() {
        if (!(self::$transport instanceof Swift_Transport)) {
            if (Cfg::get('mail_smtp_host') && Cfg::get('mail_smtp_port') ){
                self::$transport = new Swift_SmtpTransport(
                    Cfg::get('mail_smtp_host'), Cfg::get('mail_smtp_port')
                );
                self::$transport->setLocalDomain(
                    Cfg::get('site_hostname').'.'.Cfg::get('site_domain')
                );
            }
            else {
                self::$transport = new Swift_MailTransport();
            }
        }
        return self::$transport;
    }

}
