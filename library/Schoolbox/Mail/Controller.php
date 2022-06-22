<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Html\ToText as Html2Text;
use Alaress\Web\Controller\Base;
use Alaress\Mail\AbstractMailer;
use Alaress\Mail\Codec;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\UserMapper;
use Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends Base
{
    private UserMapper $userMapper;

    private \External_UserInterface $externalUser;

    public function __construct(
        UserMapper $userMapper,
        \External_UserInterface $externalUser,
    ) {
        $this->userMapper   = $userMapper;
        $this->externalUser = $externalUser;
    }

    /**
     * www/mail/createMail.php
     * @return void
     */
    public function getCreateMail()
    {
        $this->createMail($this->request->query);
    }

    /**
     * www/mail/createMail.php
     * @return void
     */
    public function postCreateMail()
    {
        $this->createMail($this->request->post);
    }

    private function createMail($params)
    {
        // Parse out list of emails passed
        // The name needs to be included in both the ID and value
        $invalidEmails = [];

        /* The replyto array key must come first, because we use it to dedupe replyto
         * senders against cc and bcc fields, to prevent them self-spamming unnecessarily
         */
        $addressLists = [
            'replyto' => [],
            'cc'      => [],
            'bcc'     => [],
        ];

        // Only students' externalIds are passed in. Building emails from there.
        $buildEmailFromId = !empty($params->get('selectedIds'));

        if ($buildEmailFromId) {
            $studentExternalIds = explode(',', $params->get('selectedIds'));
            $selectedGroups     = is_array($params->get('selectedGroups'))
                ? $params->get('selectedGroups')
                : ($params->get('selectedGroups') ? explode(',', $params->get('selectedGroups')) : []);
            /* Now get the parents of the students
             */
            if (in_array('parents', $selectedGroups)) {
                $addressLists['bcc'] = array_merge($addressLists['bcc'], $this->externalUser->getParentEmails($studentExternalIds));
            }
            if (in_array('students', $selectedGroups)) {
                $students = $this->userMapper->findByColumn('synergy_id', $studentExternalIds);
                foreach ($students as $student) {
                    $addressLists['bcc'][] = $student->getFormattedEmailDisplay();
                }
            }
            if (in_array('housemasters', $selectedGroups)) {
                $addressLists['bcc'] = array_merge($addressLists['bcc'], $this->externalUser->getHousemasterEmails($studentExternalIds));
            }
            if (in_array('tutors', $selectedGroups)) {
                $addressLists['bcc'] = array_merge($addressLists['bcc'], $this->externalUser->getTutorEmails($studentExternalIds));
            }
        }

        /* A list of email addresses that appeared in the To field
         */
        $replyToEmailAddresses = [];

        foreach ($addressLists as $type => $request) {
            if (null !== $params->get($type)) {
                $emailAddresses = AbstractMailer::parseAddressString($params->get($type));

                /* Remember some invalid emails, if any were found
                 */
                $invalidEmails[] = $emailAddresses[AbstractMailer::KEY_INVALID];

                /* Remember a list of email addresses that were in the To field
                 * There's a sequential dependency here, such that replyto must be processed
                 * before either the cc or bcc structs, as defined by $addressLists initialisation
                 */
                if ('replyto' === $type) {
                    $replyToEmailAddresses = array_keys($emailAddresses[AbstractMailer::KEY_VALID]);
                }

                if (!empty($emailAddresses[AbstractMailer::KEY_VALID])) {
                    foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $email => $name) {
                        /* For the CC and BCC fields, if an email address was already in To field,
                         * disregard it because CC'ing and BCC'ing to yourself is extreme when assigned
                         * by the system rather than the human
                         */
                        if ('replyto' === $type || !in_array($email, $replyToEmailAddresses, true)) {
                            $addressLists[$type][] = AbstractMailer::buildAddress($email, $name);
                        }
                    }
                }
            } elseif (!$buildEmailFromId && $type === 'bcc') {
                /* For the CC and BCC fields, if an email address was already in To field,
                 * disregard it because CC'ing and BCC'ing to yourself is extreme when assigned
                 * by the system rather than the human
                 */
                if (!in_array($this->getUser()->getEmail(), $replyToEmailAddresses, true)) {
                    $addressLists[$type][] = AbstractMailer::buildAddress(
                        $this->getUser()->getEmail(),
                        $this->getUser()->getFullName()
                    );
                }
            }
        }

        /**
         * build the email signature if it is defined, and the user has a schoolbox email
         * matching on user's email domain matches the smtp from address
         *
         * but dont build / add a signature if the signature field is empty
         */
        $emailBody     = '';
        $signature     = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_EMAIL_SIGNATURE);
        $tokenReplacer = new \Schoolbox\TokenReplacer\Simple();
        $emailParts    = explode('@', $this->getUser()->getEmail(), 2);
        if (
            count($emailParts) === 2 &&
            $this->getConfigLoader()->get(ConfigMapper::NAME_MAIL_ADDRESS) === $emailParts[1] &&
            '' !== trim($signature)
        ) {
            //build signature, and replace requested tokens
            $emailBody = "<p>&nbsp;</p><p>---</p>" . $tokenReplacer->replace(
                    $signature,
                    [
                        '[title]'     => $this->getUser()->getTitle(),
                        '[firstname]' => $this->getUser()->getFirstname(),
                        '[surname]'   => $this->getUser()->getLastname(),
                        //surname as that is the language we are using in admin user modify
                        '[fullname]'  => $this->getUser()->getFullName(),
                        '[email]'     => sprintf('<a href="mailto:%s">%s</a>', $this->getUser()->getEmail(), $this->getUser()->getEmail()),
                        '[position]'  => $this->getUser()->getPositionTitle(),
                    ]
                );
        }

        // Uniquify invalid email result
        $invalidEmails = array_unique(array_reduce(array_filter($invalidEmails), 'array_merge', []));
        $data          = [];

        $data['INVALID_EMAIL'] = '';
        if (\count($invalidEmails) > 0) {
            foreach ($invalidEmails as $email) {
                $data['INVALID_EMAIL'] = '"' . \Scrub::text($email) . '"';
            }
        }

        foreach ($addressLists['replyto'] as $replyToKey => $replyTo) {
            $addressLists['replyto'][$replyToKey] = Codec::decode($replyTo);
        }

        $data['SUBJECT'] = ($_REQUEST['subject'] ?? false) ? \Scrub::text($_REQUEST['subject']) : '';
        $data['BODY']    = $emailBody;
        $data['REPLYTO'] = json_encode($addressLists['replyto']);
        $data['CC']      = json_encode($addressLists['cc']);
        $data['BCC']     = json_encode($addressLists['bcc']);

        $zone = new \Schoolbox\Resource\Attachment\Zone('email-attachments');
        $zone->setMulti(true)
             ->setThumbnailSize(64)
             ->setModalTitle('Select files')
             ->setMimeFilter('*/*')
             ->setAttachPrompt('Attach files');
        $data['attachzone'] = $zone->getTwigContext();

        $uri               = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme("https");
        $data['BASE_HREF'] = $uri;


        $this->getResponse()->content->set(
            [
                'template' => '@mail/send.twig',
                'data'     => $data,
                'title'    => 'Webmail',
            ]
        );
    }

    /**
     * www/mail/send.php
     * @return void
     * @throws \Alaress\Web\Controller\Exception
     */
    public function send()
    {
        $to      = (string)$this->request->post->get('to');
        $cc      = (string)$this->request->post->get('cc');
        $bcc     = (string)$this->request->post->get('bcc');
        $subject = (string)$this->request->post->get('subject');
        $message = (string)$this->request->post->get('message');

        if (empty($to) && empty($cc) && empty($bcc)) {
            $this->sendError('No recipient provided');
        }
        $attachments = $this->request->post->get('attachment') ?? [];
        $mail        = new SimpleEmail('');

        //get users email address to use in from field
        $fromEmail = new Email($this->getUser()->getEmail());
        if (!$fromEmail->isValid()) {
            $this->sendError('Invalid email from address');
        }

        $mail->setFrom(
            $this->getUser()->getEmail(),
            $this->getUser()->formatName()
        );
        $mail->setReplyTo(
            $this->getUser()->getEmail(),
            $this->getUser()->formatName()
        );

        //if id is submitted to form convert to email
        if (is_numeric($to)) {
            try {
                $toUser = $this->userMapper->findById($to);
                $toEmail = new Email($toUser->getEmail());
                if (!$toEmail->isValid()) {
                    $this->sendError('Your email address is not valid.');
                }
                $mail->setTo(new Address($toUser->getEmail()));
            } catch (Exception $e) {
                $this->sendError('Your email address is not valid.');
            }
        } else {
            foreach ($this->getParsedAddresses($to) as $address => $name) {
                $mail->setTo(new Address($address, $name ?? ''));
            }
        }

        //check if its got no to address but has either cc or bcc
        //put the users email in the to field
        if (!stristr($to, '@') && ($bcc || $cc)) {
            $mail->setTo(new Address($this->getUser()->getEmail(), $to));
        }

        //create email header
        $mail->setSubject($subject);

        foreach ($this->getParsedAddresses($cc) as $address => $name) {
            $mail->setCc(new Address($address, $name ?? ''));
        }
        foreach ($this->getParsedAddresses($bcc) as $address => $name) {
            $mail->setBcc(new Address($address, $name ?? ''));
        }

        $converter = new Html2Text($message);
        $mail->setText($converter->getText());
        $mail->setHtml($message);

        $factory = new CollectorFactory();
        $files   = $factory->build($attachments)->getAll();

        foreach ($files as $storage) {
            $mail->addFile($storage->readStream(), $storage->getMime(), $storage->getBasename(), false);
        }

        try {
            $mail->send();
            $this->getResponse()->redirect->afterPost(
                $this->getSession()->getHistory(2)
            );
        } catch (TransportExceptionInterface $e) {
            $this->sendError($e->getMessage());
        }
    }

    /**
     * @param string $addressString
     * @return array
     */
    private function getParsedAddresses(string $addressString): array
    {
        $emailAddresses = AbstractMailer::parseAddressString(AbstractMailer::standardiseEmailSeparator($addressString));
        return $emailAddresses[AbstractMailer::KEY_VALID] ?? [];
    }
}
