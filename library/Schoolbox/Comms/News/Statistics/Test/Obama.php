<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Statistics\Test;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Statistics\Interactions;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Obama
 *
 * Welcome Back Mr. President. Your speeches constitute the basis of test articles.
 *
 * This class mass-generates News Articles with various statistics.
 * You can use the Sales data set if you change $legacy to false
 * Otherwise it'll concoct scenarios from the legacy data set
 *
 * Example usage is in www/test.php as follows:
    - Create 50 articles
    - Distribute over (24 hours * 7 days * 4 weeks) duration which is 1 month
    - Democraticness 0.35 (the smaller the number, the more left-leaning obamastats becomes)
    - 67% chance to open article


    (new \Schoolbox\Comms\News\Statistics\Test\Obama(
        $this->getDi()->get('databaseInternal'),
        \Schoolbox\UserMapper::getInstance(),
        \Schoolbox\Resource\Storage\Mapper::getInstance(),
        \Schoolbox\Comms\News\Topic\Mapper::getInstance(),
        \Schoolbox\Comms\News\Mapper::getInstance(),
        \Schoolbox\Comms\News\Audience\Mapper::getInstance(),
        \Schoolbox\Comms\News\Statistics\Mapper::getInstance(),
        \Schoolbox\Comms\News\Statistics\Cumulative\Mapper::getInstance()
    ))->generate(1, 24*7*4*4, 0.35, 67);

 *
 * @package Schoolbox\Comms\News\Statistics\Test
 */
class Obama
{
    private const NUM_WORDS_PER_TITLE = 5;
    private const NUM_PARAGRAPHS_PER_BODY = 4;

    /* Switch for changing between datasets
     */
    private $legacy = true;

    /* Mappers
     */
    private $databaseInternal;
    private $userMapper;
    private $storageMapper;
    private $topicMapper;
    private $newsMapper;
    private $audienceMapper;
    private $statisticsMapper;
    private $statisticsCumulativeMapper;

    /* Properties
     */
    private $users;
    private $acceptedAt;
    private $acceptedBy;
    private $submittedAt;
    private $expiresAt;
    private $titleSource;
    private $titles;
    private $words;
    private $hashes;

    /**
     * @var Collection|News\Topic\Model[]
     */
    private $topics;

    /**
     * @var Interactions
     */
    private $ico;

    /* All the referrers that currently exist
     */
    private $referrers = [
        News\Statistics\Mapper::REFERRER_DASHBOARD,
        News\Statistics\Mapper::REFERRER_HOMEPAGE,
        News\Statistics\Mapper::REFERRER_INDEX,
        News\Statistics\Mapper::REFERRER_ARCHIVE,
        News\Statistics\Mapper::REFERRER_EMAIL
    ];

    /* Statistical summary
     */
    private $summary = [
        'role' => [
            'open' => [
                'staff' => 0,
                'parent' => 0,
                'student' => 0,
                'guest' => 0
            ],
        ],
        'numOpens' => 0
    ];


    /**
     * Obama constructor.
     *
     * @param IConnector                        $databaseInternal
     * @param UserMapper                        $userMapper
     * @param Storage\Mapper                    $storageMapper
     * @param News\Topic\Mapper                 $topicMapper
     * @param News\Mapper                       $newsMapper
     * @param News\Audience\Mapper              $audienceMapper
     * @param News\Statistics\Mapper            $statisticsMapper
     * @param News\Statistics\Cumulative\Mapper $statisticsCumulativeMapper
     */
    public function __construct(
        IConnector $databaseInternal,
        UserMapper $userMapper,
        Storage\Mapper $storageMapper,
        News\Topic\Mapper $topicMapper,
        News\Mapper $newsMapper,
        News\Audience\Mapper $audienceMapper,
        News\Statistics\Mapper $statisticsMapper,
        News\Statistics\Cumulative\Mapper $statisticsCumulativeMapper
    ) {
        $this->databaseInternal = $databaseInternal;
        $this->userMapper = $userMapper;
        $this->storageMapper = $storageMapper;
        $this->topicMapper = $topicMapper;
        $this->newsMapper = $newsMapper;
        $this->audienceMapper = $audienceMapper;
        $this->statisticsMapper = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;

        $this->init();
    }


    /**
     * Init all the pieces of our presidential puzzle
     */
    private function init(): void
    {
        $this->initUsers();
        $this->initTitles();
        $this->initDates();
        $this->initBody();
        $this->initImages();
        $this->initTopics();

        /* Helper
         */
        $this->ico = new Interactions($this->databaseInternal, $this->statisticsMapper, $this->statisticsCumulativeMapper);
    }


    /**
     * Generic supply of users
     */
    private function initUsers(): void
    {
        $this->users = $this->userMapper->findAll()->getAll();
    }


    /**
     * Figure out some titles that are at least 5 letters long
     */
    private function initTitles(): void
    {
        $this->titleSource = explode(' ', str_replace(["\n", '.', ','], ' ', "
            Now don't get me wrong. I don't know what Bible they're reading, but it doesn't jibe with my version. The President vetoed a similar plan, but he doesn't have the last word, and we're going to keep at it, until we bring this war to an end. The truth is, on issue after issue that would make a difference in your lives - on health care and education and the economy - Senator McCain has been anything but independent.
            Go into the collar counties around Chicago, and people will tell you they don't want their tax money wasted, by a welfare agency or by the Pentagon. The UCC is still listening. He does not say health care or the economy. This country is more decent than one where a woman in Ohio, on the brink of retirement, finds herself one illness away from disaster after a lifetime of hard work. You don't protect Israel and deter Iran just by talking tough in Washington. That is why I ordered the removal of our combat brigades by next August.
            And all across the country, communities of faith are sponsoring day care programs, building senior centers, and in so many other ways, taking part in the project of American renewal. Out of work? Tough luck. And Democrats, we must also admit that fulfilling America's promise will require more than just money. The Bush-McCain foreign policy has squandered the legacy that generations of Americans - Democrats and Reprivateans - have built, and we are here to restore that legacy. They have killed people of different faiths - more than any other, they have killed Muslims. Indeed, faith should bring us together.
            But as Americans, we also know that this is a nation of laws, and we cannot have those laws broken when more than 2,000 people cross our borders illegally every day. You make a big election about small things.
            But we also know our conscience cannot rest so long as the war goes on in Iraq. And there's another issue we must confront as well. Given my background, my politics, and my professed values and ideals, there will no doubt be those for whom my statements of condemnation are not enough. This has bred more fear and mistrust.
        "));

        $this->titles = array_values(array_filter(array_map(function($string) {
            return ucwords($string);
        }, $this->titleSource), function($string) {
            return \strlen($string) > self::NUM_WORDS_PER_TITLE;
        }));
    }


    /**
     * Generic dates and details
     */
    private function initDates(): void
    {
        $this->acceptedAt = \Alaress_Date_Format::now();
        $this->acceptedBy = $this->users[0];
        $this->submittedAt = \Alaress_Date_Format::now();
        $this->expiresAt = \Alaress_Date_Format::now()->addDays(14);
    }


    /**
     * Generic body content
     */
    private function initBody(): void
    {
        $this->words = [
            "The document they produced was eventually signed but ultimately unfinished. And as so many generations have come to realize over the course of the two-hundred and twenty one years since a band of patriots signed that document in Philadelphia, that is where the perfection begins.",
            "It's a story that hasn't made me the most conventional candidate. And occasionally it finds voice in the church on Sunday morning, in the pulpit and in the pews. It's because John McCain doesn't get it. And throughout history, Islam has demonstrated through words and deeds the possibilities of religious tolerance and racial equality.",
            "They're ready to turn the page on the old politics and the old policies - whether it's the war in Iraq or the health care crisis we're in, or a school system that's leaving too many kids behind despite the slogans. And today, whenever I find myself feeling doubtful or cynical about this possibility, what gives me the most hope is the next generation - the young people whose attitudes and beliefs and openness to change have already made history in this election. Unlike Afghanistan, Iraq was a war of choice that provoked strong differences in my country and around the world.",
            "Now don't get me wrong. Our universities and our culture are the envy of the world, but that's not what keeps the world coming to our shores. So let there be no doubt: Islam is a part of America. The Palestinian Authority must develop its capacity to govern, with institutions that serve the needs of its people. Rather than remain trapped in the past, I have made it clear to Iran's leaders and people that my country is prepared to move forward.",
            "Yet what we also understand is that our values should express themselves not just through our churches or synagogues, temples or mosques; they should express themselves through our government. But it does find voice in the barbershop or around the kitchen table.",
            "But my grandfather had larger dreams for his son. I understand you switched venues at considerable expense and inconvenience because of unfair labor practices at the place you were going to be having this synod. These people are a part of me. They've worked hard all their lives, many times only to see their jobs shipped overseas or their pension dumped after a lifetime of labor. We can play Reverend Wright's sermons on every channel, every day and talk about them from now until the election, and make the only question in this campaign whether or not the American people think that I somehow believe or sympathize with his most offensive words. There need not be contradiction between development and tradition.",
            "Given my background, my politics, and my professed values and ideals, there will no doubt be those for whom my statements of condemnation are not enough. What we have already achieved gives us hope - the audacity to hope - for what we can and must achieve tomorrow. This too is part of America's promise - the promise of a democracy where we can find the strength and grace to bridge divides and unite in common effort. The dream of opportunity for all people has not come true for everyone in America, but its promise exists for all who come to our shores - that includes nearly seven million American Muslims in our country today who enjoy incomes and education that are higher than average. government has gone to court to protect the right of women and girls to wear the hijab, and to punish those who would deny it. Around the world, the Jewish people were persecuted for centuries, and anti-Semitism in Europe culminated in an unprecedented Holocaust.",
            "That is the true genius of America - a faith in simple dreams, an insistence on small miracles. That we can have an idea and start our own business without paying a bribe. What would be needed were Americans in successive generations who were willing to do their part - through protests and struggle, on the streets and in the courts, through a civil war and civil disobedience and always at great risk - to narrow that gap between the promise of our ideals and the reality of their time. This is a difficult responsibility to embrace. That does not lessen my commitment, however, to governments that reflect the will of the people. That is why I am committed to working with American Muslims to ensure that they can fulfill zakat.",
            "I'm not talking about blind optimism here - the almost willful ignorance that thinks unemployment will go away if we just don't think about it, or the health care crisis will solve itself if we just ignore it. I understand you switched venues at considerable expense and inconvenience because of unfair labor practices at the place you were going to be having this synod. But I'm hopeful because I think there's an awakening taking place in America.",
            "It's a journey that takes us back to our nation's founding, when none other than a UCC church inspired the Boston Tea Party and helped bring an Empire to its knees. But my personal story is not so unique.",
            "Some will see this as an attempt to justify or excuse comments that are simply inexcusable. It requires all Americans to realize that your dreams do not have to come at the expense of my dreams; that investing in the health, welfare, and education of black and brown and white children will ultimately help all of America prosper. You have shown what history teaches us - that at defining moments like this one, the change we need doesn't come from Washington. Since the Islamic Revolution, Iran has played a role in acts of hostage-taking and violence against U.S.",
            "I've gone to some of the best schools in America and lived in one of the world's poorest nations. They weren't simply a religious leader's effort to speak out against perceived injustice. They came of age in the late fifties and early sixties, a time when segregation was still the law of the land and opportunity was systematically constricted. And as someone who watched my mother argue with insurance companies while she lay in bed dying of cancer, I will make certain those companies stop discriminating against those who are sick and need care the most. It is easy to point fingers - for Palestinians to point to the displacement brought by Israel's founding, and for Israelis to point to the constant hostility and attacks throughout its history from within its borders as well as beyond.",
            "But my journey is part of a larger journey - one shared by all who've ever sought to apply the values of their faith to our society. But we shouldn't use our humility as an excuse for inaction. And yet Al Qaeda chose to ruthlessly murder these people, claimed credit for the attack, and even now states their determination to kill on a massive scale. Around the world, the Jewish people were persecuted for centuries, and anti-Semitism in Europe culminated in an unprecedented Holocaust.",
            "At the same time, Israelis must acknowledge that just as Israel's right to exist cannot be denied, neither can Palestine's. In ancient times and in our times, Muslim communities have been at the forefront of innovation and education. But we should choose the right path, not just the easy path.",
            "I stand here today, grateful for the diversity of my heritage, aware that my parents' dreams live on in my two precious daughters. If your hopes have been dashed again and again, then it's best to stop hoping, and settle for what you already know. I know there is debate about this issue.",
            "The day after Pearl Harbor my grandfather signed up for duty; joined Patton's army, marched across Europe. If you're working forty hours a week, you shouldn't be living in poverty.",
            "There is real evil and hardship and pain and suffering in the world and we should be humble in our belief that we can eliminate them. Ironically, this quintessentially American - and yes, conservative - notion of self-help found frequent expression in Reverend Wright's sermons. Anyway, Ashley finishes her story and then goes around the room and asks everyone else why they're supporting the campaign. All of us have a responsibility to work for the day when the mothers of Israelis and Palestinians can see their children grow up without fear; when the Holy Land of three great faiths is the place of peace that God intended it to be; when Jerusalem is a secure and lasting home for Jews and Christians and Muslims, and a place for all of the children of Abraham to mingle peacefully together as in the story of Isra, when Moses, Jesus, and Mohammed (peace be upon them) joined in prayer.",
            "It requires all Americans to realize that your dreams do not have to come at the expense of my dreams; that investing in the health, welfare, and education of black and brown and white children will ultimately help all of America prosper. I'll help our auto companies re-tool, so that the fuel-efficient cars of the future are built right here in America. Not with so much work to be done. At this moment, in this election, we must pledge once more to march into the future. It was innovation in Muslim communities that developed the order of algebra; our magnetic compass and tools of navigation; our mastery of pens and printing; our understanding of how disease spreads and how it can be healed. And so in that spirit, let me speak as clearly and plainly as I can about some specific issues that I believe we must finally confront together.",
            "After the war, they studied on the G.I. It's been several months now since I announced I was running for president. And we should close Guantanamo Bay and stop tolerating the torture of our enemies. The press has scoured every exit poll for the latest evidence of racial polarization, not just in terms of white and black, but black and brown as well. Americans are ready to join with citizens and governments; community organizations, religious leaders, and businesses in Muslim communities around the world to help our people pursue a better life.",
            "Thank you very much everybody."
        ];
    }


    /**
     * Some randomly chosen images
     */
    private function initImages(): void
    {
        $this->hashes = $this->storageMapper->findIn('ext', ['png', 'jpg', 'jpeg', 'JPG']);
    }


    /**
     * Topics
     */
    private function initTopics(): void
    {
        $this->topics = $this->topicMapper->findAll();
    }


    /**
     * Manufacture random user
     *
     * @return mixed
     * @throws \Exception
     */
    private function getRandomUser()
    {
        return $this->users[random_int(0, \count($this->users) - 1)];
    }


    /**
     * Manufacture title
     *
     * @return string
     * @throws \Exception
     */
    private function getRandomTitle(): string
    {
        $title = [];

        for ($k = 0; $k < self::NUM_WORDS_PER_TITLE; $k++) {
            $title[] = $this->titles[random_int(0, \count($this->titles)-1)];
        }

        return implode(' ', $title);
    }


    /**
     * Manufacture body
     *
     * @return string
     * @throws \Exception
     */
    private function getRandomBody(): string
    {
        $body = [];

        for ($k = 0; $k < self::NUM_PARAGRAPHS_PER_BODY; $k++) {
            $body[] = "<p>{$this->words[random_int(0, \count($this->words)-1)]}</p>";
        }

        return implode("\n", $body);
    }


    /**
     * Manufacture body
     *
     * @return string
     * @throws \Exception
     */
    private function getRandomBlurb(): string
    {
        return "<p>{$this->words[random_int(0, \count($this->words)-1)]}</p>";
    }


    /**
     * Manufacture feature image
     *
     * @return null|Storage\Model
     * @throws \Exception
     */
    private function getRandomImage(): ?Storage\Model
    {
        return $this->hashes[random_int(0, \count($this->hashes) - 1)];
    }


    /**
     * Choose a random set of zero or more topics for this article
     * @throws \Exception
     */
    private function getRandomTopics(): Collection
    {
        $numTopics = random_int(0, $this->topics->count());

        $topics = [];

        for ($j = 0; $j < $numTopics; $j++) {
            $randomTopic = $this->getRandomTopic();

            $topics[$randomTopic->getId()] = $randomTopic;
        }

        return new Collection($topics);
    }


    /**
     * Find a random topic
     *
     * @return News\Topic\Model
     * @throws \Exception
     */
    private function getRandomTopic(): News\Topic\Model
    {
        return $this->topics->getOffset(\random_int(0, $this->topics->count()-1));
    }


    /**
     * Find a random referrer
     *
     * @param bool $skewedStatistics
     * @return string
     * @throws \Exception
     */
    private function getRandomReferrer(bool $skewedStatistics): string
    {
        if ($skewedStatistics) {
            return $this->referrers[$this->getWeightedRandom(0, \count($this->referrers) - 1, 0.5)];
        }

        return $this->referrers[\random_int(0, \count($this->referrers)-1)];
    }


    /**
     * Used to get a folderId for testing homepage news
     *
     * @return int
     */
    private function getFolder(): int
    {
        if ($this->legacy) {
            return 43;
        }

        return 83;
    }


    /**
     * Weighted int
     * https://stackoverflow.com/questions/445235/generating-random-results-by-weight-in-php
     *
     * @param $min
     * @param $max
     * @param $gamma
     * @return int
     */
    private function getWeightedRandom($min, $max, $gamma): int {
        $offset= $max-$min+1;

        return (int)floor($min+ (lcg_value() ** $gamma) * $offset);
    }


    /* Creationals
     */

    /**
     * Create an article
     *
     * @param int $i
     * @return News\Model
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createArticle(int $i): News\Model
    {
        $model = $this->newsMapper->createModel()
                                  ->setAuthor($this->getRandomUser())
                                  ->setTitle($this->getRandomTitle())
                                  ->setBody($this->getRandomBody())
                                  ->setBlurb($this->getRandomBlurb())
                                  ->setTopics($this->getRandomTopics())
                                  ->setFeatureImage($this->getRandomImage())
                                  ->setSubmittedAt($this->submittedAt)
                                  ->setAcceptedAt($this->acceptedAt)
                                  ->setAcceptedBy($this->getRandomUser())
                                  ->setPublishAt(\Alaress_Date_Format::now()->addDays(-1 * $i))
                                  ->setExpiresAt($this->expiresAt);

        $this->newsMapper->save($model);

        return $model;
    }


    /**
     * Create audience
     *
     * @param News\Model $model
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createAudience(News\Model $model): void
    {
        /** @var News\Audience\Model $audience */
        $audience = $this->audienceMapper->createModel();
        $audience->setCommsNewsArticle($model)
                 ->setEntityType('user')
                 ->setEntityValue((string)$this->getRandomUser()->getId())
                 ->setRelationship('self');

        $this->audienceMapper->save($audience);

        /* Add generic Folder audience
         */
        $audience = $this->audienceMapper->createModel();
        $audience->setCommsNewsArticle($model)
                 ->setEntityType('folder')
                 ->setEntityValue((string)$this->getFolder())
                 ->setRelationship('self');

        $this->audienceMapper->save($audience);
    }


    /**
     * For every article:
     *   randomly decide how many users experienced $model
     *     for every user, give them a random quantity of impression+referrer sets
     *       for every set, save it and update cumulative total
     *       for every set, decide whether there's an open
     *         if there is an open, save it using the aforementioned referrer
     *           increment overall time and continue
     *
     * @param News\Model $model
     * @param int        $duration Distribute stats over this duration
     * @param float      $democraticness
     * @param int        $directOpenLikelihood
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createStatistics(
        News\Model $model,
        int $duration,
        float $democraticness,
        int $directOpenLikelihood = 10
    ): void {
        /* At least half of your user population is involved
         */
        $minBias = 0.75;

        /* zero-indexed count
         */
        $indexablePopulation = \count($this->users)-1;

        $numUsers = \random_int((int)floor($indexablePopulation*$minBias), $indexablePopulation);
        new \Alaress_Probe(str_pad(' ',8)."{$numUsers} users were randomly selected for interaction generation");

        $referrers = News\Statistics\Mapper::REFERRERS;

        for ($i = 0; $i < $numUsers; $i++) {

            /** @var User $currentUser */
            $currentUser = $this->getRandomUser();

            /* Decide whether this was person opened a thing
             */
            if (\random_int(0, 100) < $directOpenLikelihood) {
                $randomReferrer = $referrers[\rand(0, \count($referrers)-1)];

                /* Pass an actual value if we chose an unknown referrer
                 */
                if ($randomReferrer === News\Statistics\Mapper::REFERRER_EMPTY) {
                    $referrer = null;
                } else {
                    $referrer = $randomReferrer;
                }

                /* Jitter this around
                 */
                $r = $this->ico->saveOpen($model, $this->generateStatisticDate($duration, $democraticness), $currentUser->getId(), $currentUser->getRole()->getType(), $currentUser->getRole()->getId(), $referrer);

                if ($r !== false) {
                    $this->summary['role'][News\Statistics\Mapper::TYPE_OPEN][$currentUser->getRole()->getType()]++;
                    $this->summary['numOpens']++;
                }

                new \Alaress_Probe(str_pad(' ', 12) . "{$currentUser->getFullName()} opened directly onto carpet using {$directOpenLikelihood}% and referrer {$randomReferrer}");
            } else {
                new \Alaress_Probe(str_pad(' ', 12) . "{$currentUser->getFullName()} didn't open the article");
            }
        }
    }


    /**
     * Change the date of a generated stat
     *
     * Democraticness: the smaller the number, the more democratic Obama becomes
     * 0.15 is very democratic
     * 1 is not
     *
     * @param int $duration
     * @param     $democraticness
     * @return \Alaress_Date_Format
     */
    private function generateStatisticDate(int $duration, $democraticness): \Alaress_Date_Format
    {
        $val = $this->getWeightedRandom(1, $duration, $democraticness);

        $difference = -1 * $val * 3600;

        $newTime = \Alaress_Date_Format::now();
        $newTime->addSeconds($difference);

        return $newTime;
    }


    /**
     * Artificially reddit this article with an $articleNum incrementor that's used to
     * create discussion_thread_ids because I didn't find a mapper/model anywhere obvious
     *
     * @param News\Model $model
     * @return int
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createComments(News\Model $model): int
    {
        /* Create a thread
         */
        $threadMapper = \Schoolbox\Discussion\Thread\Mapper::getInstance();

        $thread = $threadMapper->createModel()->setOpen(true);

        $threadMapper->save($thread);

        /* Make some commentary
         */
        $numComments = rand(1, 10);

        $commentMapper = \Schoolbox\Discussion\Comment\Mapper::getInstance();

        for ($i = 0; $i < $numComments; $i++) {
            $comment = $commentMapper->createModel()
                ->setContent($this->getRandomBody())
                ->setAuthor($this->getRandomUser())
                ->setThread($thread)
                ->setCreatedAt(\Alaress_Date_Format::now()->addDays($this->getWeightedRandom(1,5, 1)));

            $commentMapper->save($comment);
        }

        /* Associate thread
         */
        \Schoolbox\Comms\News\Mapper::getInstance()->save($model->setDiscussionThread($thread));

        return $numComments;
    }


    /**
     * Spawn this many random articles
     * Use a prime number for pagination and demonstration purposes
     *
     * @param int   $numArticles
     * @param int   $duration Number of hours for which to populate data
     * @param float $democraticness
     * @param int   $directOpenLikelihood
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function generate(int $numArticles, int $duration, float $democraticness, int $directOpenLikelihood): void
    {
        new \Alaress_Probe("Creating {$numArticles} articles using {$directOpenLikelihood}% Open rate over {$duration} hours");

        /* First article instance to go in, is the oldest
         */
        for ($i = $numArticles; $i > 0; $i--) {
            $articleNum = $numArticles - $i + 1;

            /* $i is an integer which can be passed to createArticle to indicate how many days in the past
             * this article should be published; $i = 10 means "publish date is now minus ten days"
             * You can give it $i in the iteration which signifies one article per day si published
             * or you can give it some other integer value instead
             */
            $model = $this->createArticle($i);
            new \Alaress_Probe("Created article {$articleNum} with id {$model->getId()}: {$model->getTitle()}");

            $this->createAudience($model);
            new \Alaress_Probe(str_pad(' ',4)."Added audience for article {$articleNum} with id {$model->getId()}: {$model->getTitle()}");

            $this->createStatistics($model, $duration, $democraticness, $directOpenLikelihood);
            new \Alaress_Probe(str_pad(' ',4)."Generated randomised statistics for article {$articleNum} with id {$model->getId()}: {$model->getTitle()}");

            $numComments = $this->createComments($model);
            new \Alaress_Probe(str_pad(' ', 4)."Commented {$numComments} random comments for article {$articleNum} with id {$model->getId()}: {$model->getTitle()}");
        }

        /* Statistical summary
         */
        new \Alaress_Probe($this->summary);
    }
}
