<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

use Schoolbox\Mapper;

class NSMapper extends Mapper
{
    const NS_PASTORAL                = 'pastoral';
    const NS_PASTORAL_COMMENT        = 'pastoral_comment';
    const NS_PASTORAL_LOG            = 'pastoral_log';
    const NS_DUEWORK                 = 'duework';
    const NS_OLDDUEWORK              = 'work';
    const NS_ONLINE_ASSESS           = 'assess';
    const NS_HOMEPAGE_BLOG           = 'homepage_blog';
    const NS_EPORTFOLIO_BLOG         = 'eportfolio_blog';
    const NS_FORUM                   = 'forum';
    const NS_NEWS                    = 'news';
    const NS_CALENDAR                = 'calendar';
    const NS_WIKI                    = 'wiki';
    const NS_GROUP                   = 'group';
    const NS_CMS                     = 'cms';
    const NS_SSTREAM                 = 'sstream';
    const NS_EOTC                    = 'eotc';
    const NS_RESOURCE_BOOKING        = 'resource_booking';
    const NS_REPORT_CARD             = 'report_card';
    const NS_SPORTS_FIXTURES         = 'sports_fixtures';
    const NS_SPORTS_FIXTURES_IMPORT  = 'sports_fixtures_import';
    const NS_MESSAGE                 = 'message';
    const NS_TIMETABLE               = 'timetable';
    const NS_UPS                     = 'ups';
    const NS_NEWS_DISCUSSION_COMMENT = 'news_discussion_comment';
    const NS_EVIDENCE                = 'moment';
    const NS_EVIDENCE_COMMENT        = 'moment_comment';
    const NS_BADGE                   = 'badge';


    /**
     * @inheritDoc
     */
    protected function mapping()
    {
        $this->hasTable('msg_namespace');
        $this->hasModel(NS::class);

        $this->hasMap(
            [
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            ]
        );
        $this->hasMap(
            [
                'column'   => 'slug',
                'property' => 'slug',
                'type'     => self::TYPE_STRING,
            ]
        );
        $this->hasMap(
            [
                'column'   => 'label',
                'property' => 'label',
                'type'     => self::TYPE_STRING,
            ]
        );
    }

    /**
     * @param string $slug
     * @return null|NS
     */
    public function findOneBySlug(string $slug): ?NS
    {
        try {
            return $this->findOneByColumn('slug', $slug);
        } catch (Mapper\Exception $e) {
            return null;
        }
    }
}
