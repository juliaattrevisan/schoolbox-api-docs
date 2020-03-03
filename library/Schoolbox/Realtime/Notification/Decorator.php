<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Realtime\Notification;

use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress\Html\ToText as Html2Text;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\Realtime\Message\NSMapper;
use TextFormatter;
use UnderflowException;

/**
 * Class Decorator
 *
 * @package Schoolbox\Realtime\Notification
 */
class Decorator extends NotificationBundle
{

    /**
     * How many params can be appended before they get slid under "and n others"
     */
    const STACK_LIMIT = 3;


    /**
     * @param Notification $notification
     */
    public function setNotification(Notification $notification): void
    {
        $this->notifications = array();
        $this->appendNotification($notification);
    }


    /**
     * @param int $limit
     * @param int $dir
     * @return array
     */
    protected function toArray(int $limit = 0, int $dir = SORT_DESC): array
    {
        $array = array();

        foreach ($this->getNotifications($limit, $dir) as $notification) {
            $hash = $notification->getId();

            $array[$hash] = array(
                'title'     => array(
                    'text'   => $notification->getTitle(),
                    'params' => $this->renderParametersAsArray($notification),
                ),
                'namespace' => $notification->getNamespace(),
                'icon'      => $notification->getIcon(),
                'href'      => $notification->getHref(),
                'meta'      => $notification->getMeta(),
                'time'      => $notification->getTimestamp()
            );
        }

        return $array;
    }


    /**
     * Render the stored array of notification instances as a Twig context array (for sending via email)
     *
     * @param array $coreContext   the base context of the template to be rendered, if any
     * @param int   $limit         the max number of notifications to return. The default is 'all'
     * @param int   $sortDirection the direction in which to sort the messages, by timestamp
     * @return array                a context Twig can parse
     */
    public function renderAsTwigContext(array $coreContext = [], int $limit = 0, int $sortDirection = SORT_DESC): array
    {
        $array = $this->toArray();
        $fqdn  = isset($coreContext['config']['fqdn']) ? 'https://' . $coreContext['config']['fqdn'] : '';

        foreach ($this->getNotifications($limit, $sortDirection) as $n) {
            // compile the title
            try {
                $array[$n->getId()]['title'] = $this->renderTitleAsHtml($n, $fqdn);
                $array[$n->getId()]['icon']  = $this->getUnprotectedIcon($n);

                // force links to be fully qualified
                foreach (array('icon', 'href') as $field) {
                    $value = $array[$n->getId()][$field];
                    if (strlen($value) && $value[0] == '/') {
                        $array[$n->getId()][$field] = $fqdn . $value;
                    }
                }

                // check metadata for links as well
                foreach ($array[$n->getId()]['meta'] as $k => $value) {
                    if (is_string($value) && strlen($value) && $value[0] == '/') {
                        $array[$n->getId()]['meta'][$k] = $fqdn . $value;
                    }
                }

            } catch (UnderflowException $e) {
                unset($array[$n->getId()]);
            }
        }

        $context = array();

        foreach ($array as $id => $msg) {
            // create data structure for emails
            switch ($msg['namespace']) {
                case NSMapper::NS_DUEWORK:
                case NSMapper::NS_ONLINE_ASSESS:
                    $ns = 'work';
                    break;
                case NSMapper::NS_NEWS:
                    $ns = 'news';
                    break;
                default:
                    $ns = 'activity';
                    break;
            }

            // for work, aggregate by day; everything else, one row per item
            if ($ns == 'work') {
                $daystamp = (int)($msg['time'] / 86400);
                $context['groups'][$ns]['rows'][$daystamp]['messages'][$id] = $msg;
            } else {
                $context['groups'][$ns]['rows'][/* blank on purpose */]['messages'][$id] = $msg;
            }
        }

        /* Apply group headings as required
         * Localise terminology
         */
        $rosettaStone = $this->rosettaStone->build(L10nMapper::getInstance());

        $headings = [
            'work'     => 'Schoolwork',
            'news'     => $rosettaStone->translate('news', false, true),
            'activity' => 'Activity'
        ];

        foreach ($headings as $ns => $heading) {
            if (isset($context['groups'][$ns])) {
                $context['groups'][$ns]['heading'] = $heading;
            }
        }

        // and now, the sorting
        foreach ($context['groups'] as &$g) {
            foreach ($g['rows'] as &$r) {
                ksort($r['messages']);
            }
            if ($sortDirection == SORT_DESC) {
                krsort($g['rows']);
            } else {
                ksort($g['rows']);
            }
        }
        krsort($context['groups']);

        return $context;
    }


    /**
     * SitePage is dead. Long live SitePage.
     *
     * @deprecated
     * @param int $limit
     * @param int $dir
     * @return array
     */
    public function renderAsSitePageArray(int $limit = 0, int $dir = SORT_DESC): array
    {
        $context = $this->toArray();

        foreach ($this->getNotifications($limit, $dir) as $n) {
            // compile the title
            try {
                $context[$n->getId()]['title'] = $this->renderTitleAsHtml($n);

                // convert the time into human readable
                $date                             = new \Alaress_Date_Format($n->getTimestamp(), 'U');
                $context[$n->getId()]['longtime'] = $date->getLong();
                $context[$n->getId()]['timeonly'] = $date->getTimeOnly();
            } catch (UnderflowException $e) {
                // TODO log this somewhere
                unset($context[$n->getId()]);
            }
        }

        return $context;
    }


    /**
     * Render the stored array of notification instances as an array that can be sent down a websocket
     *
     * @param bool $asUnread - by default, the message will be marked as coming from the 'read' context
     * @param int  $limit    - the maximum number of notifications to be returned, default is 'all'
     * @param int  $dir      - by default, sorting order is reverse chronological
     * @return array
     */
    public function renderAsSocketArray(bool $asUnread = false, int $limit = 0, int $dir = SORT_DESC): array
    {
        $result = array();

        foreach ($this->getNotifications($limit, $dir) as $notification) {
            $id = $notification->getId();
            $result[$id] = array(
                'id'        => $id,
                'body'      => $notification->getTitle(),
                'icon'      => $notification->getIcon(),
                'href'      => $notification->getHref(),
                'params'    => $this->renderParametersAsArray($notification),
                'meta'      => $notification->getMeta(),
                'timestamp' => $notification->getTimestamp(),
                'status'    => $asUnread ? 'unread' : 'read'
            );

            // if meta.body is set, convert it to plaintext and truncate it
            if (isset($result[$id]['meta']['body'])) {
                $plain = new Html2Text($result[$id]['meta']['body']);
                $result[$id]['meta']['body'] = TextFormatter::cutSentence($plain->getText(), 48);
            }
        }

        return $result;
    }


    /**
     * @param Notification $notification
     * @return array
     */
    public function renderParametersAsArray(Notification $notification): array
    {
        $array = array();
        foreach ($notification->getParameters() as $token => $timeParams) {
            // perform deduping here: we only want the latest of each distinct param
            $latest = array();
            // sort params by reverse timestamp to give precedence to most recent
            krsort($timeParams);
            /** @var ParameterInterface[] $params */
            foreach ($timeParams as $time => $params) {
                foreach ($params as $param) {
                    if ($param->getMode() === ParameterInterface::STACKING_APPEND) {
                        $param->setTimestamp($time);
                    }
                    $serial = $param->jsonSerialize();
                    $hash   = sha1(json_encode($serial));

                    if (!isset($latest[$hash]) || $time >= $latest[$hash]) {
                        $latest[$hash]        = $time;
                        $array[$token][$hash] = $serial;
                    }
                }
            }
        }
        return $array;
    }


    /**
     * Translate a the :tokenised title of a notification into an HTML string
     *
     * @param Notification $notification
     * @param string       $fqdn
     * @throws UnderflowException
     * @return string
     * @throws UnderflowException
     */
    public function renderTitleAsHtml(Notification $notification, string $fqdn = ''): string
    {
        $text = $notification->getTitle();

        foreach ($notification->getParameters() as $token => $timeParams) {

            if (!count($timeParams)) {
                // if this occurs, it's a bug in the notifier that created this notification.
                throw new UnderflowException("Message '$text' contains no params for token '$token'");
            }
            // sort params by reverse timestamp to give precedence to most recent
            krsort($timeParams);

            /** @var ParameterInterface[] $stack */
            $stack = [];
            $subst = '';

            foreach ($timeParams as $time => $params) {
                /** @var ParameterInterface[] $params */
                // probably a bit of a code smell, but retrieves first value.
                // equivalent to $timeParams[0][0] but where the keys are unknown
                $subst = $this->param2html(reset($params), $fqdn);

                foreach ($params as $param) {
                    switch ($param->getMode()) {
                        case ParameterInterface::STACKING_REPLACE:
                        case ParameterInterface::STACKING_REPLACE_NO_DECORATION:
                            $subst = $this->param2html($param, $fqdn);
                            break;
                        case ParameterInterface::STACKING_APPEND:
                            $param->setTimestamp($time);
                            $stack[] = $param;
                            break;
                        case ParameterInterface::STACKING_IGNORE:
                        default:
                            // ignore? ok, ignored.
                            break;
                    }
                }

            }

            // use the stack if it was populated
            if (count($stack)) {
                $subst = $this->stack2html($stack, $fqdn);
            }

            $text = str_replace(":$token", $subst, $text);
        }

        return $text;
    }


    /**
     * @param Notification $notification
     * @return string
     */
    public function renderTitleAsPlaintext(Notification $notification): string
    {
        // this might be considered cheating
        return html_entity_decode(strip_tags($this->renderTitleAsHtml($notification)), ENT_QUOTES);
    }


    /**
     * Translate a parameter into an HTML anchor tag
     *
     * @param ParameterInterface $param
     * @param string $fqdn
     * @return string
     */
    protected function param2html(ParameterInterface $param, string $fqdn = ''): string
    {
        $text = \Scrub::text($param->getText());

        if ($param->getHref()) {
            return sprintf('<a href="%s%s">%s</a>', $fqdn, $param->getHref(), $text);
        }
        if ($param->getMode() === $param::STACKING_REPLACE_NO_DECORATION) {
            return $text;
        }
        return "<a>{$text}</a>";
    }


    /**
     * Translate an array of parameters into "x, y, and n others..."
     *
     * @param ParameterInterface[] $params
     * @param string               $fqdn
     * @return string
     */
    protected function stack2html(array $params, string $fqdn): string
    {
        $bits = array();

        /** @var ParameterInterface[] $params */
        $params = array_unique($params, SORT_STRING);

        // take the first n params, and tuck the rest away
        for ($i = 0; $i < self::STACK_LIMIT - 1; ++$i) {
            if (isset($params[0])) {
                $bits[] = $this->param2html(array_shift($params), $fqdn);
            }
        }

        switch (count($params)) {
            case 0:
                // do nothing
                break;
            case 1:
                // if it's just the one, include it
                $bits[] = $this->param2html($params[0], $fqdn);
                break;
            default:
                // if there's anything more left over, aggregate it
                $others = array();
                foreach ($params as $p) {
                    $others[] = $p->getText();
                }
                $bits[] = sprintf('<a data-others="%s">%s others</a>', implode(';', $others), count($params));
                break;
        }

        if (count($bits) >= 2) {
            $parts = array(
                implode(', ', array_slice($bits, 0, count($bits) - 1)),
                $bits[count($bits) - 1]
            );
        } else {
            $parts = $bits;
        }

        return implode(' and ', $parts);
    }


    /**
     * Check a notification's icon and return a globally visible URL (ie. no portraits)
     *
     * @param Notification $notification
     * @return string
     */
    protected function getUnprotectedIcon(Notification $notification): string
    {
        // icon cannot be a protected link
        $isPortrait = strpos($notification->getIcon(), '/portrait') === 0;
        $isBlank    = $notification->getIcon() == '';

        if ($isPortrait || $isBlank) {
            // change to be the generic icon for the namespace
            return '/images/icon.php?size=64&icon=' . $notification->getNamespace();
        }
        return $notification->getIcon();
    }

}
