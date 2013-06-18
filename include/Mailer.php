<?php

/************************************************************
 *                                                           *
 *         Copyright (c) 2006 Lektec Consulting                *
 *                                                           *
 ************************************************************/

require_once( 'Mail.php' );
require_once( 'Mail/mail.php' );
require_once( 'Mail/mime.php' );
require_once( dirname(__FILE__) . '/Template.php' );
require_once( dirname(__FILE__) . '/Config.php' );

class Mailer {

    function Mailer ($template, $subject = "", $to = "", $host = '') {

        //setup header
        $this->headers = '';
        $this->setFrom(Cfg::get('mail_from_address'), Cfg::get('mail_from_name'));
        $this->headers['MessageID'] = '<' . md5(mktime()) . '@lektecmailer>';

        $this->setTo($to);
        $this->setSubject($subject);

        //setup HTML template
        $this->template = new Template( Config::get('template_dir') );
        $this->t_label = "tplBODY";
        $this->loadTemplate($template);

        //setup defaults
        if($host) {
            $this->template->set_var('SITE_DOMAIN', $host);
        }
        else {
            $this->template->set_var('SITE_DOMAIN', $_SERVER['HTTP_HOST']);
        }

        //setup MIME email
        $this->email = new Mail_mime( "\n" );

        //$this->text = "Please view this email in a HTML capable client.";
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
        $this->email->addAttachment($file, $type, $filename, $isFile);
    }

    function addImage ($file, $type = 'application/octet') {
        //add inline image to email
        $this->email->addHTMLImage($file, $type);
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

        //construct body
        //$this->email->setTXTBody($this->text);
        $this->email->setHTMLBody($this->composeHTML());

        //retrieve email
        $body = $this->email->get(array(
            'html_charset' => 'utf-8',
            'text_charset' => 'utf-8',
            'head_charset' => 'utf-8',
        ));
        $this->email->_headers['To'] = $this->to; //	mime_mail doe not overwrite its headers with the ones you send
        $hdrs = $this->email->headers($this->headers);

        //send email

        if(Cfg::get('mail_smtp_host') && Cfg::get('mail_smtp_port') ){
            $smtpSettings = array(
                    'host' => Cfg::get('mail_smtp_host'),
                    'port' => Cfg::get('mail_smtp_port'),
                    'localhost' => Cfg::get('site_hostname').'.'.Cfg::get('site_domain')
            );

            $mail =& Mail::factory('smtp', $smtpSettings);
        }
        else {
            $mail =& Mail::factory('mail');
        }
        $return = $mail->send($this->to, $hdrs, $body);

        // prevent memory leak
        unset($mail);

        return $return;
    }

}