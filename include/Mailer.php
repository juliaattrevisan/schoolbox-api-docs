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
     * Instance of mail transporter.
     *
     * @var Swift_Transport
     */
    protected static $transport;

    function Mailer ($template, $subject = "", $to = "", $host = '') {

        $this->email = new Swift_Message();

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
    }

    function setFrom($email, $name = "") {
        if ($name) {
            $this->email->setFrom($email, $name);
        }
        else {
            $this->email->setFrom($email);
        }
        $this->email->setReturnPath($email);
    }

    /**
     * Convenience method for setting this mailer's From address as the
     * currently logged in user.
     *
     * @todo dependency inject session instead of direct reference
     *
     * @return $this
     */
    public function setFromAsCurrentUser() {
        $email_object  = new Email();
        $email_address = $email_object->formatEmailFromArray($_SESSION);
        $this->setFrom($email_address, trim($_SESSION['firstname'].' '.$_SESSION['lastname']));
        return $this;
    }

    function setTo ($to) {
        if ( is_array($to) ) {
            if ( $to['email'] ) {
                if ( $to['name'] ) {
                    $this->email->addTo($to['email'], $to['name']);
                }
                else {
                    $this->email->addTo($to['email']);
                }
            }
            else {

                $this->to = '';
                foreach ( $to as $email ) {
                    if ( $email['name'] ) {
                        $this->email->addTo($email['email'], $email['name']);
                    }
                    else {
                        $this->email->addTo($email['email']);
                    }
                }
            }
        }
        else {
            $this->email->setTo(self::parseAddressString($to));
        }
    }

    function setSubject ($subject) {
        $this->email->setSubject($subject);
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

    /**
     * Parses a list of email addresses out of a string, into a format that can
     * be passed directly to Swift_Message->setTo().
     *
     * @param string $string
     *
     * @return array
     */
    public static function parseAddressString($string) {
        $address_list = imap_rfc822_parse_adrlist($string, Cfg::get('site_hostname').'.'.Cfg::get('site_domain'));
        $return_list  = array();
        foreach ($address_list as $address) {
            if ($address->host !== '.SYNTAX-ERROR.') {
                $to    = $address->mailbox.'@'.$address->host;
                $alias = property_exists($address, 'personal') ? $address->personal : null;
                $return_list[$to] = $alias;
            }
        }
        return $return_list;
    }

}
