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

use Alaress\Session\Session;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use \SitePage;
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
use Schoolbox\Sportsfixtures\Team\Model as Team;

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
     * @var SitePage
     */
    protected $sitepage;

    /**
     * Delegate constructor.
     * @param SitePage $sitepage
     */
    public function __construct(SitePage $sitepage)
    {
        $this->setSitePage($sitepage);
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
     * Gets a collection of matches with relevant teams and players attached, sorted by 'startAt'
     *
     * @return Collection|Match[]
     */
    public function getParentDashboardData()
    {
        /** @var Collection|Match[] $teams */
        $matches = new Collection();

        /** @var Session $session */
        $session = $this->getSitePage()->getDi()->get('session');
        $children = $session->get('children');

        if(!is_array($children) || !count($children)){
            return $matches;
        }

        $childIds = [];
        foreach ($children as $child) {
            $childIds[] = $child['id'];
        }

        /** @var Collection|TeamPlayer[] $teamPlayers */
        $teamPlayers = $this->getTeamPlayerMapper()->findByColumn('user_id', $childIds);

        /** @var Collection|Team[] $teams */
        $teams = new Collection();
        foreach($teamPlayers as $teamPlayer){
            $team = $teamPlayer->getTeam();
            if(!$teams[$team->getId()]){
                $teams[$team->getId()] = $team;
            }
            $team->getPlayers()->add($teamPlayer->getUser());
        }

        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $teams->keys());
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $teams->keys());

        /**
         * @var Collection|Match[] $all_matches
         */
        $all_matches  = $home_matches->mergeUnique($away_matches);

        foreach($all_matches as $match){
            if($match->getStartAt()->isFuture()){
                $matches[$match->getId()] = $match;
            }

            // Can we link a home team ?
            if($teams[$match->getHomeTeam()->getId()]){
                $match->setHomeTeam($teams[$match->getHomeTeam()->getId()]);
            }

            // Can we link an away team ?
            if($teams[$match->getAwayTeam()->getId()]){
                $match->setHomeTeam($teams[$match->getAwayTeam()->getId()]);
            }
        }

        return $this->sortMatches($matches);
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
     * @param bool $getHistorical Whether to also return matches that have been played
     * @return Collection|Match[]
     */
    public function getTeamMatches($teamId, $getHistorical)
    {
        $matches = new Collection();
        if ($teamId == 0) {
            return $matches;
        }

        // Get the team we are looking at

        $team = $this->getTeamMapper()->findById($teamId);

        // Load all home and away matches for the team

        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $team->getId());
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $team->getId());

        $all_matches  = $home_matches->mergeUnique($away_matches, 'id');

        foreach($all_matches as $match){
            if($getHistorical || $match->getStartAt()->isFuture()){
                $matches->add($this->loadExternals($match));
            }
        }

        return $this->sortMatches($matches);
    }


    /**
     * @param Collection|TeamPlayer[] $teamPlayers
     * @return Collection|Match[]
     */
    public function getMatches(Collection $teamPlayers)
    {
        $matches = new Collection();

        $teamIds = [];
        foreach($teamPlayers as $teamPlayer){
            $teamIds[] = $teamPlayer->getTeam()->getId();
        }

        $home_matches = $this->getMatchMapper()->findByColumn('home_team_id', $teamIds);
        $away_matches = $this->getMatchMapper()->findByColumn('away_team_id', $teamIds);
        $all_matches  = $home_matches->mergeUnique($away_matches);

        // Whack all of the matches into an array
        /** @var Match $match */
        foreach ($all_matches as $match) {
            if ($match->getStartAt()->isFuture()) {
                $matches->add($this->loadExternals($match));
            }
        }

        return $this->sortMatches($matches);
    }

    /**
     * Sort matches chronologically
     *
     * @param Collection|Match[] $matches
     * @return Collection|Match[]
     */
    public function sortMatches(Collection $matches)
    {
        // Sort matches by date order
        $matches->sortByCallback(function($a, $b) {
            /** @var Match $a */
            /** @var Match $b */
            return ($a->getStartAt()->compare($b->getStartAt()));
        });

        return $matches;
    }

    /**
     * Load external school names
     * @param Match $match
     * @return Match
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

    /**
     * @param SitePage $sitepage
     */
    protected function setSitePage(SitePage $sitepage){
        $this->sitepage = $sitepage;
    }

    /**
     * @return SitePage
     */
    protected function getSitePage(){
        return $this->sitepage;
    }
}