<?php
/**
 * Delegate class for logic that is common to both the dashboard and homepage
 * tile components.
 *
 * This would be a trait if Schoolbox was on PHP 5.4+, but it isn't yet so what
 * can I do?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Component;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Player\Model as TeamPlayer;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use \Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
use \Schoolbox\TagCloud\Mapper as TagCloudMapper;

class ComponentDelegate
{
    /**
     * @var UserMapper;
     */
    protected $userMapper;

    /**
     * @var FolderMapper;
     */
    protected $folderMapper;

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var MatchMapper
     */
    protected $matchMapper;

    /**
     * @var VenueMapper
     */
    protected $venueMapper;

    /**
     * @var ItineraryItemMapper
     */
    protected $itineraryItemMapper;

    /**
     * @var TagCloudMapper
     */
    protected $schoolTagCloudMapper;

    /**
     * Delegate constructor.
     */
    public function __construct()
    {
        $this->setUserMapper(UserMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setItineraryItemMapper(ItineraryItemMapper::getInstance());
        $this->setVenueMapper(VenueMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
        $this->setSchoolTagCloudMapper(TagCloudMapper::getInstance('sportsfixtures_school', 'id', 'name'));
    }

    /**
     * @return array
     */
    public function getParentDashboardData()
    {
        $matches = [];

        foreach ($_SESSION["children"] as $child) {

            $childTeams = $this->getTeamPlayerMapper()->findByColumn('user_id', $child["id"]);
            $matchesForChild = $this->getMatches($childTeams);

            foreach ($matchesForChild as $matchCurrent) {

                if (!isset($matches[$matchCurrent->getId()])) {
                    $matches[$matchCurrent->getId()] = [];
                    $matches[$matchCurrent->getId()]["home"]  = false;
                    $matches[$matchCurrent->getId()]["away"]  = false;
                    $matches[$matchCurrent->getId()]["match"] = $matchCurrent;
                    $matches[$matchCurrent->getId()]["teams"] = [
                        $matchCurrent->getHomeTeam()->getId() => [
                            'team'     => $matchCurrent->getHomeTeam(),
                            'children' => [],
                        ],
                        $matchCurrent->getAwayTeam()->getId() => [
                            'team'     => $matchCurrent->getAwayTeam(),
                            'children' => [],
                        ],
                    ];
                }

                foreach ($childTeams as $team) {
                    $teamId = $team->getTeam()->getId();
                    $isHome = ($teamId == $matchCurrent->getHomeTeam()->getId());
                    $isAway = ($teamId == $matchCurrent->getAwayTeam()->getId());
                    if ($isHome) {
                        $matches[$matchCurrent->getId()]["home"] = true;
                    }
                    if ($isAway) {
                        $matches[$matchCurrent->getId()]["away"] = true;
                    }
                    if ($isHome || $isAway) {
                        $matches[$matchCurrent->getId()]["teams"][$teamId]["children"][$team->getUser()->getId()] = $team->getUser();
                    }
                }
            }
        }

        return [
            'role' => 'parent',
            'matches' => $matches
        ];
    }

    /**
     * @param User $user
     * @return array
     */
    public function getStudentDashboardData($user)
    {
        // Get all teams the current user is in
        $matches        = [];
        $studentTeams   = $this->getTeamPlayerMapper()->findByColumn('user_id', $user->getId());
        $studentMatches = $this->getMatches($studentTeams);
        foreach ($studentMatches as $matchCurrent) {
            $matches[$matchCurrent->getId()] = [
                'home'  => false,
                'away'  => false,
                'match' => $matchCurrent,
                'teams' => [
                    $matchCurrent->getHomeTeam()->getId() => [
                        'team' => $matchCurrent->getHomeTeam(),
                    ],
                    $matchCurrent->getAwayTeam()->getId() => [
                        'team' => $matchCurrent->getAwayTeam(),
                    ],
                ],
            ];
            foreach ($studentTeams as $team) {
                $teamId = $team->getTeam()->getId();
                if ($teamId == $matchCurrent->getHomeTeam()->getId()) {
                    $matches[$matchCurrent->getId()]["home"] = true;
                }
                if ($teamId == $matchCurrent->getAwayTeam()->getId()) {
                    $matches[$matchCurrent->getId()]["away"] = true;
                }
            }
        }

        return [
            'user'      => $user,
            'role'      => 'student',
            'matches'   => $matches
        ];
    }

    /**
     * @param int $teamId
     * @return array
     */
    public function getTeamData($teamId)
    {
        if ($teamId == 0) {
            return [];
        }

        // Get current users role

        $matches = [];

        // Get the team we are looking at

        $team = $this->getTeamMapper()->findById($teamId);

        // Load all home and away matches for the team

        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $team->getId());
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $team->getId());

        // Whack all of the matches into an array

        /** @var Match $match */
        foreach ($home_matches as $match) {
            if ($match->getStartAt()->isFuture()) {
                $matches[] = $this->loadExternals($match);
            }
        }

        foreach ($away_matches as $match) {
            if ($match->getStartAt()->isFuture()) {
                $matches[] = $this->loadExternals($match);
            }
        }

        return [
            'matches' => $this->sortMatches($matches)
        ];
    }


    /**
     * @param $teamPlayers
     * @return array
     */
    public function getMatches($teamPlayers) {
        $matches = [];

        /** @var TeamPlayer $teamPlayer */
        foreach ($teamPlayers as $teamPlayer) {

            // Get all home and away matches for the current team
            $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $teamPlayer->getTeam()->getId());
            $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $teamPlayer->getTeam()->getId());

            // Whack all of the matches into an array
            /** @var Match $match */
            foreach ($home_matches as $match) {
                if ($match->getStartAt()->isFuture()) {
                    $matches[] = $this->loadExternals($match);
                }
            }

            foreach ($away_matches as $match) {
                if ($match->getStartAt()->isFuture()) {
                    $matches[] = $this->loadExternals($match);
                }
            }
        }

        return $this->sortMatches($matches);
    }

    /**
     * Sort matches chronologically
     *
     * @param array $matches
     * @return mixed
     */
    public function sortMatches($matches) {
        // Sort matches by date order
        usort($matches, function($a, $b) {
            /** @var Match $a */
            /** @var Match $b */
            return ($a->getStartAt()->compare($b->getStartAt()));
        });

        return $matches;
    }

    /**
     * Load external school names
     * @param Match $match
     * @return mixed
     */
    public function loadExternals($match) {
        if ($match->getAwayTeam()->getId() == null) {
            $match->getAwayTeam()->setName(
                $this->getSchoolTagCloudMapper()->getTagName($match->getAwaySchoolId())
            );
        }

        if ($match->getHomeTeam()->getId() == null) {
            $match->getHomeTeam()->setName(
                $this->getSchoolTagCloudMapper()->getTagName($match->getHomeSchoolId())
            );
        }

        $match->getDivision()->getSport()->setName(
            $this->getSportMapper()->findById($match->getDivision()->getSport()->getId())->getName()
        );

        return $match;
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @return TeamPlayerMapper
     */
    public function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param  TeamPlayerMapper $teamPlayerMapper
     * @return $this
     */
    public function setTeamPlayerMapper(TeamPlayerMapper $teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
        return $this;
    }

    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param  TeamMapper $teamMapper
     * @return $this
     */
    public function setTeamMapper(TeamMapper $teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return MatchMapper
     */
    public function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
    }

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
    }

    /**
     * @return ItineraryItemMapper
     */
    public function getItineraryItemMapper()
    {
        return $this->itineraryItemMapper;
    }

    /**
     * @param ItineraryItemMapper $itineraryItemMapper
     */
    public function setItineraryItemMapper($itineraryItemMapper)
    {
        $this->itineraryItemMapper = $itineraryItemMapper;
    }

    /**
     * @return VenueMapper
     */
    public function getVenueMapper()
    {
        return $this->venueMapper;
    }

    /**
     * @param VenueMapper $venueMapper
     */
    public function setVenueMapper($venueMapper)
    {
        $this->venueMapper = $venueMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param  FolderMapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
    }

    /**
     * @return TagCloudMapper
     */
    protected function getSchoolTagCloudMapper()
    {
        return $this->schoolTagCloudMapper;
    }

    /**
     * @param TagCloudMapper $schoolTagCloudMapper
     */
    protected function setSchoolTagCloudMapper($schoolTagCloudMapper)
    {
        $this->schoolTagCloudMapper = $schoolTagCloudMapper;
    }
}