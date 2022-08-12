<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Html\ToText as Html2Text;
use Alaress\Web\Controller\Base;
use Alaress\Mail\Mailer;
use Alaress\Mail\Codec;
use Aura\Web\Request\Values;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Profile\ValidEmailVisible;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends Base
{
    public function __construct(
        private UserMapper $userMapper,
        private \External_UserInterface $externalUser,
        private FilesystemProxy $filesystemProxy,
        private EmailFactory $emailFactory,
        private FolderMapper $folderMapper,
        private UserAccessMapper $userAccessMapper
    ) {
    }

    /**
     * Display email form populated with requested parameters
     *
     * @return void
     * @since 22.1
     * @api   GET /mail/create
     */
    public function getCreateMail()
    {
        $this->createMail($this->request->query);
    }

    /**
     * Display email form populated with posted data
     *
     * @return void
     * @since 22.1
     * @api   POST /mail/create
     */
    public function postCreateMail()
    {
        $this->createMail($this->request->post);
    }

    /**
     * www/mail/createMail.php
     *
     * @param Values $params
     * @return void
     */
    private function createMail(Values $params)
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

        /* A list of email addresses that appeared in the To field
         */
        $replyToEmailAddresses = [];

        foreach ($addressLists as $type => $request) {
            if (null === $params->get($type)) {
                continue;
            }
            $emailAddresses = Mailer::parseAddressString($params->get($type), $this->getDefaultHost());

            /* Remember some invalid emails, if any were found
             */
            $invalidEmails = array_merge($emailAddresses[Mailer::KEY_INVALID], $invalidEmails);

            /* Remember a list of email addresses that were in the To field
             * There's a sequential dependency here, such that replyto must be processed
             * before either the cc or bcc structs, as defined by $addressLists initialisation
             */
            if ('replyto' === $type) {
                $replyToEmailAddresses = array_keys($emailAddresses[Mailer::KEY_VALID]);
            }

            /**
             * @var string  $email
             * @var Address $address
             */
            foreach ($emailAddresses[Mailer::KEY_VALID] as $email => $address) {
                /* For the CC and BCC fields, if an email address was already in To field,
                 * disregard it because CC'ing and BCC'ing to yourself is extreme when assigned
                 * by the system rather than the human
                 */
                if ('replyto' === $type || !in_array($email, $replyToEmailAddresses, true)) {
                    $addressLists[$type][] = $address->toString();
                }
            }
        }
        // Add current user to bcc, if not already in TO
        $isCurrentUserInTo = in_array($this->getUser()->getEmail(), $replyToEmailAddresses, true);

        $folder = null;
        if ($params->get('folderId')) {
            try {
                $folder = $this->folderMapper->findById((int)$params->get('folderId'));
            } catch (MapperException $e) {
            }
        }

        $addressListFromIds  = $this->findAddressesFromIds($params, $folder);
        $addressLists['bcc'] = array_merge($addressLists['bcc'], $addressListFromIds);

        // Add current user if not exists, remove current user if exists in both to and bcc
        if (!$isCurrentUserInTo && !in_array($this->getUser()->getFormattedEmailDisplay(), $addressLists['bcc'])) {
            $addressLists['bcc'][] = $this->getUser()->getFormattedEmailDisplay();
        } elseif ($isCurrentUserInTo) {
            $addressLists['bcc'] = array_diff($addressLists['bcc'], [
                $this->getUser()->getEmail(),
                $this->getUser()->getFormattedEmailDisplay(),
            ]);
        }

        $emailBody = $this->getEmailBody();

        // Uniquify invalid email result
        $invalidEmails = array_unique($invalidEmails);

        foreach ($addressLists['replyto'] as $replyToKey => $replyTo) {
            $addressLists['replyto'][$replyToKey] = Codec::decode($replyTo);
        }

        $zone = new \Schoolbox\Resource\Attachment\Zone('email-attachments');
        $zone->setMulti(true)
             ->setThumbnailSize(64)
             ->setModalTitle('Select files')
             ->setMimeFilter('*/*')
             ->setAttachPrompt('Attach files');
             ->useImportExpiryDate();
        $data['attachzone'] = $zone->getTwigContext();

        $uri = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme("https");

        $this->getResponse()->content->set(
            [
                'template' => '@mail/send.twig',
                'data'     => [
                    'invalidEmails' => $invalidEmails,
                    'SUBJECT'       => $params->get('subject'),
                    'BODY'          => $emailBody,
                    'REPLYTO'       => json_encode(array_values($addressLists['replyto'])),
                    'CC'            => json_encode(array_values($addressLists['cc'])),
                    'BCC'           => json_encode(array_values($addressLists['bcc'])),
                    'attachzone'    => $zone->getTwigContext(),
                    'BASE_HREF'     => $uri,
                    'folder'        => $folder,
                ],
                'title'    => 'Webmail',
            ]
        );
    }

    /**
     * Send out email
     * www/mail/send.php
     * @return void
     * @throws \Alaress\Web\Controller\Exception
     * @throws \League\Flysystem\FilesystemException
     * @api   POST /mail/send
     * @since 22.1
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
        $mail        = $this->emailFactory->buildSimpleEmail($subject, '');

        //get users email address to use in from field

        try {
            $fromEmail = new Address($this->getUser()->getEmail(), $this->getUser()->formatName());
            $mail->setFrom($fromEmail);
            $mail->addReplyTo($fromEmail);
        } catch (RfcComplianceException | LogicException $e) {
            $this->sendError('Invalid email from address');
        }

        //if id is submitted to form convert to email
        if (is_numeric($to)) {
            try {
                $toUser  = $this->userMapper->findById($to);
                $toEmail = new Email($toUser->getEmail());
                if (!$toEmail->isValid()) {
                    $this->sendError('Your email address is not valid.');
                }
                $mail->setTo(new Address($toUser->getEmail()));
            } catch (MapperException $e) {
                $this->sendError('Your email address is not valid.');
            }
        } else {
            foreach ($this->getParsedAddresses($to) as $address) {
                $mail->setTo($address);
            }
        }

        //check if its got no to address but has either cc or bcc
        //put the users email in the to field
        if (!stristr($to, '@') && ($bcc || $cc)) {
            $mail->setTo(new Address($this->getUser()->getEmail(), $to));
        }

        //create email header
        $mail->setSubject($subject);

        foreach ($this->getParsedAddresses($cc) as $address) {
            $mail->setCc($address);
        }
        foreach ($this->getParsedAddresses($bcc) as $address) {
            $mail->setBcc($address);
        }

        $converter = new Html2Text($message);
        $mail->setText($converter->getText());
        $mail->setHtml($message);

        $factory = new CollectorFactory();
        $files   = $factory->build($attachments)->getAll();

        foreach ($files as $storage) {
            $mail->addFile($this->filesystemProxy->readStream($storage), $storage->getMime(), $storage->getBasename(), false);
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
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($addressString), $this->getDefaultHost());
        return $emailAddresses[Mailer::KEY_VALID] ?? [];
    }

    /**
     * @return string
     */
    private function getDefaultHost(): string
    {
        return trim($this->getConfigLoader()->get('site_hostname')) . '.' . trim($this->getConfigLoader()->get('site_domain'));
    }

    /**
     * Display feedback form
     * www/emailForm.php
     * @return void
     * @throws TransportExceptionInterface
     * @throws \Alaress\Web\Controller\Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @since 22.1
     * @api   POST /mail/feedback
     */
    public function postFeedback()
    {
        /* This form used to be for generic emails, now only used for the feedback form
           It requires the following variables;
                    template - template filename to be used
                    email - email address of sender
           The following are optional but recommended variables
                    subject - subject of email to be sent
                    name - name of sender
                    confirmation - location to redirect user to after submission, ie a confirmation page
        */

        $recaptcha = new \ReCaptcha(
            $this->getConfigLoader()->get('recaptcha_public_api_key'),
            $this->getConfigLoader()->get('recaptcha_private_api_key'),
        );

        if (!$this->getSession()->getId() && !$recaptcha->verify($this->request->post->get('g-recaptcha-response'))) {
            $this->sendError("The Human Checker (reCAPTCHA) failed.");
        }

        $name    = $this->getUser()->getFullName();
        $email   = $this->request->post->get('email');
        $replyTo = null;
        if ($email) {
            try {
                $replyTo = new Address($email, $name);
            } catch (RfcComplianceException | LogicException $e) {
                // noop
            }
        }

        if ($replyTo && preg_match("/^[a-zA-Z0-9\/\-\_]+\.html$/", $this->request->post->get('template'))) {
            foreach ($this->request->post->getArrayCopy() as $key => $var) {
                if (!$this->checkSpamHeaders($var)) {
                    $this->getResponse()->redirect->to('/');
                }
                $key = strtoupper($key);

                $context[$key] = $var;
            }

            $context['NAME']            = $name;
            $context['hideUnsubscribe'] = true;

            $subject = $this->getConfigLoader()->get('title') . ' Feedback - ' . $this->request->post->get('subject');
            $to      = new Address($this->getConfigLoader()->get(ConfigMapper::NAME_MAIL_SUPPORT_ADDRESS), $this->getConfigLoader()->get(ConfigMapper::NAME_MAIL_FROM_NAME));
            $email   = $this->emailFactory->buildTwigEmail('feedback.twig', $subject, $to);
            $email->addReplyTo($replyTo);
            $email->setContext($context);
            $email->send();
            /*  if($_POST['receipt']) {
                    $receiptHandler = new Mailer($_POST['receipt'], $_POST['receipt_subject'], $_POST['email']);
                    $receiptHandler->setVar($output);
                    $receiptHandler->setVar('SITE_DOMAIN', $_SERVER['HTTP_HOST']);
                    $receiptHandler->send();
                }
            */
        }

        if (preg_match("/^[a-zA-Z0-9\/\-\_]+/", $_POST['confirmation'])) {
            $this->getResponse()->redirect->to($_POST['confirmation']);
        } else {
            $this->getResponse()->redirect->to('/feedback/thank-you');
        }
    }

    /**
     * @param string $text
     * @return bool
     */
    private function checkSpamHeaders(string $text): bool
    {
        //checks for common spammer injected headers
        if (stristr($text, 'Content-Type:') !== false) {
            return false;
        }
        if (stristr($text, 'MIME-Version:') !== false) {
            return false;
        }
        if (stristr($text, 'Bcc:') !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param Values     $params
     * @param Model|null $folder
     * @return array
     */
    private function findAddressesFromIds(Values $params, ?Model $folder): array
    {
        // Expecting either studentExternalIds, userIds or folderId
        if ($params->get('studentExternalIds')) {
            $studentExternalIds = explode(',', (string)$params->get('studentExternalIds'));
            $users              = $this->userMapper->findByColumn('synergy_id', $studentExternalIds);
        } elseif ($params->get('userIds')) {
            $users = $this->userMapper->findByColumn('id', explode(',', (string)$params->get('userIds')));
        } elseif ($params->get('folderId')) {
            try {
                $users = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false, false);
            } catch (MapperException $e) {
                return [];
            }
        } else {
            return [];
        }

        $selectedGroups = [];
        if ($params->get('selectedGroups')) {
            $selectedGroups = is_array($params->get('selectedGroups'))
                ? $params->get('selectedGroups')
                : explode(',', (string)$params->get('selectedGroups'));
        }
        $emailVisible = new ValidEmailVisible($this->externalUser, $this->getConfigLoader());
        $viewer       = $this->getUser();
        $targetUsers  = $users->filter(
            function ($user) use ($emailVisible, $viewer) {
                /** @var User $user */
                if ($viewer->getRole()->isParent()) {
                    return $emailVisible->isSatisfiedByParent($user, $viewer);
                }
                return $emailVisible->isSatisfiedByRole($user->getRole()->getType(), $viewer->getRole(), $viewer->isSuperuser());
            }
        );

        $addressLists = $targetUsers->map(
            static function ($user) {
                /** @var User $user */
                return $user->getFormattedEmailDisplay();
            }
        )->getAll();

        if (!$viewer->getRole()->isStaff() || empty($selectedGroups) || $selectedGroups === ['students']) {
            return $addressLists;
        }

        /* Now get the parents of the students
         */
        $studentExternalIds ??= $users->filter(
            function ($user) {
                /** @var User $user */
                return $user->getRole()->isStudent();
            }
        )->getPropertyValues('externalId');

        if (in_array('parents', $selectedGroups)) {
            $addressLists = array_merge($addressLists, $this->externalUser->getParentEmails($studentExternalIds));
        }
        if (in_array('housemasters', $selectedGroups)) {
            $addressLists = array_merge($addressLists, $this->externalUser->getHousemasterEmails($studentExternalIds));
        }
        if (in_array('tutors', $selectedGroups)) {
            $addressLists = array_merge($addressLists, $this->externalUser->getTutorEmails($studentExternalIds));
        }
        return $addressLists;
    }

    /**
     * @return string
     */
    private function getEmailBody(): string
    {
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
            $emailBody = "<p>&nbsp;</p><p>---</p>"
                . $tokenReplacer->replace(
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
        return $emailBody;
    }
}
