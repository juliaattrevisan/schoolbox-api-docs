<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Html\ToText as Html2Text;
use Alaress\Web\Controller\Base;
use Aura\Web\Request\Values;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Profile\ValidEmailVisible;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
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
    // available parameters to pass to createMail, similar to relationship of news audience
    private const SELF            = 'self';
    private const PARENTS_OF      = 'parents_of';
    private const HOUSEMASTERS_OF = 'housemasters_of';
    private const TUTORS_OF       = 'tutors_of';

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

        $addressLists = [
            'replyto' => [],
            'cc'      => [],
            'bcc'     => [],
        ];

        // Validate email addresses provided and remove current user
        foreach (array_keys($addressLists) as $type) {
            if (null === $params->get($type)) {
                continue;
            }
            $emailAddresses = imap_rfc822_parse_adrlist($params->get($type), '');
            foreach ($emailAddresses as $emailAddress) {
                try {
                    $address = $this->convertAddress($emailAddress);
                    if ($this->getUser()->getEmail() === $address->getAddress()) {
                        // do not add current user to any lists for now. deal with this later
                        continue;
                    }
                    $addressLists[$type][] = $address->toString();
                } catch (Exception $e) {
                    $invalidEmails[] = $e->getMessage();
                    continue;
                }
            }
        }

        // Find addresses from ids and add to corresponding field
        $folder = null;
        // we don't want the top level folder as well...
        if ($params->get('folderId') && (int)$params->get('folderId') !== IFolder::ROOT_ID) {
            try {
                $folder = $this->folderMapper->findById((int)$params->get('folderId'));
            } catch (MapperException $e) {
            }
        }

        $addressListFromIds    = $this->findAddressesFromIds($params, $folder);
        $target                = $params->get('target') ?? 'bcc';
        $addressLists[$target] = array_merge($addressLists[$target], $addressListFromIds);

        // Add current user
        // - to replyto, if replyto is empty
        // - to bcc, if reply to is not empty
        if (empty($addressLists['replyto'])) {
            // If TO field is empty, add current user to the TO field
            // and make sure that the current user does not exist in CC and BCC
            if ($folder) {
                try {
                    $replyTo = new Address($this->getUser()->getEmail(), $folder->getName());
                } catch (RfcComplianceException | LogicException $e) {
                    // noop
                }
            }
            $addressLists['replyto'][] = isset($replyTo) ? $replyTo->toString() : $this->getUser()->getFormattedEmailDisplay();
            // Remove current user, if exists in cc and bcc
            foreach (['cc', 'bcc'] as $type) {
                $addressLists[$type] = array_diff($addressLists[$type], [
                    $this->getUser()->getEmail(),
                    $this->getUser()->getFormattedEmailDisplay(),
                ]);
            }
        } elseif (
            !in_array($this->getUser()->getEmail(), $addressLists['bcc'])
            && !in_array($this->getUser()->getFormattedEmailDisplay(), $addressLists['bcc'])
        ) {
            $addressLists['bcc'][] = $this->getUser()->getFormattedEmailDisplay();
        }

        $emailBody = $this->getEmailBody();

        // Uniquify invalid email result
        $invalidEmails = array_unique($invalidEmails);

        $zone = new \Schoolbox\Resource\Attachment\Zone('email-attachments');
        $zone->setMulti(true)
             ->setThumbnailSize(64)
             ->setModalTitle('Select files')
             ->setMimeFilter('*/*')
             ->setAttachPrompt('Attach files')
             ->useImportExpiryDate();

        $uri = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme("https");

        $student = null;
        if ($params->get('studentId')) {
            try {
                $student = $this->userMapper->findById($params->get('studentId'));
            } catch (MapperException $e) {
                // noop
            }
        }

        $this->getResponse()->content->set(
            [
                'template' => '@mail/send.twig',
                'data'     => [
                    'invalidEmails' => $invalidEmails,
                    'subject'       => $params->get('subject'),
                    'body'          => $emailBody,
                    'replyTo'       => json_encode(array_values($addressLists['replyto'])),
                    'cc'            => json_encode(array_values($addressLists['cc'])),
                    'bcc'           => json_encode(array_values($addressLists['bcc'])),
                    'attachzone'    => $zone->getTwigContext(),
                    'baseHref'      => $uri,
                    'folder'        => $folder,
                    'student'       => $student,
                    'title'         => $params->get('title'),
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
        $mail        = $this->emailFactory->buildSimpleEmail($subject, null);

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
            foreach (imap_rfc822_parse_adrlist($to, '') as $addressObj) {
                try {
                    $mail->setTo($this->convertAddress($addressObj));
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        //check if its got no to address but has either cc or bcc
        //put the users email in the to field
        if (!stristr($to, '@') && ($bcc || $cc)) {
            $mail->setTo(new Address($this->getUser()->getEmail(), $to));
        }

        //create email header
        $mail->setSubject($subject);

        foreach (imap_rfc822_parse_adrlist($cc, '') as $addressObj) {
            try {
                $mail->setCc($this->convertAddress($addressObj));
            } catch (Exception $e) {
                continue;
            }
        }
        foreach (imap_rfc822_parse_adrlist($bcc, '') as $addressObj) {
            try {
                $mail->setBcc($this->convertAddress($addressObj));
            } catch (Exception $e) {
                continue;
            }
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
            $to      = new Address(
                $this->getConfigLoader()->get(ConfigMapper::NAME_MAIL_SUPPORT_ADDRESS),
                $this->getConfigLoader()->get(ConfigMapper::NAME_MAIL_FROM_NAME)
            );
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
     * @param Values      $params
     * @param Folder|null $folder
     * @return array
     */
    private function findAddressesFromIds(Values $params, ?Folder $folder): array
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

        if ($params->get('selectedGroups')) {
            $selectedGroups = is_array($params->get('selectedGroups'))
                ? $params->get('selectedGroups')
                : explode(',', (string)$params->get('selectedGroups'));
        } else {
            $selectedGroups = [self::SELF];
        }

        $emailVisible = new ValidEmailVisible($this->externalUser, $this->getConfigLoader());
        $viewer       = $this->getUser();

        $addressLists = [];
        if (in_array(self::SELF, $selectedGroups)) {
            $targetUsers = $users->filter(
                function ($user) use ($emailVisible, $viewer) {
                    /** @var User $user */
                    return $emailVisible->isSatisfiedBy($user, $viewer);
                }
            );

            $addressLists = $targetUsers->map(
                static function ($user) {
                    /** @var User $user */
                    return $user->getFormattedEmailDisplay();
                }
            )->getAll();
        }

        if (!$viewer->getRole()->isStaff() || $selectedGroups === [self::SELF]) {
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

        if (in_array(self::PARENTS_OF, $selectedGroups) && $emailVisible->isSatisfiedByRole(Role::PARENT, $viewer->getRole(), $viewer->isSuperuser())) {
            $addressLists = array_merge($addressLists, $this->externalUser->getParentEmails($studentExternalIds));
        }
        if (in_array(self::HOUSEMASTERS_OF, $selectedGroups)) {
            $addressLists = array_merge($addressLists, $this->externalUser->getHousemasterEmails($studentExternalIds));
        }
        if (in_array(self::TUTORS_OF, $selectedGroups)) {
            $addressLists = array_merge($addressLists, $this->externalUser->getTutorEmails($studentExternalIds));
        }
        return array_unique(array_filter($addressLists));
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
                        '[title]'         => $this->getUser()->getTitle(),
                        '[firstname]'     => $this->getUser()->getFirstname(),
                        '[surname]'       => $this->getUser()->getLastname(),
                        '[givenname]'     => $this->getUser()->getGivenName(),
                        '[preferredname]' => $this->getUser()->getPreferredName(),
                        '[fullname]'      => $this->getUser()->getFullName(),
                        '[email]'         => sprintf('<a href="mailto:%s">%s</a>', $this->getUser()->getEmail(), $this->getUser()->getEmail()),
                        '[position]'      => $this->getUser()->getPositionTitle(),
                    ]
                );
        }
        return $emailBody;
    }

    /**
     * Convert output of imap_rfc822_parse_adrlist to Symfony Address object
     * @param \stdClass $addressObj
     * @return Address
     */
    private function convertAddress(\stdClass $addressObj): Address
    {
        try {
            return new Address($addressObj->mailbox . '@' . $addressObj->host, $addressObj->personal ?? '');
        } catch (RfcComplianceException | InvalidArgumentException $e) {
            throw new Exception($addressObj->mailbox . (!empty($addressObj->host) ? '@' . $addressObj->host : ''));
        }
    }
}
