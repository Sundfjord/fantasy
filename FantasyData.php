<?php

require_once(__DIR__.'/Curler.php');

/**
 * Class to perform multiCurl queries
 */
class FantasyData
{
    const FANTASY_STATIC_DATA_URL = 'https://fantasy.premierleague.com/api/bootstrap-static/';
    const FANTASY_TEAM_URL = 'https://fantasy.premierleague.com/api/entry/';
    const FANTASY_CLASSIC_LEAGUE_URL = 'https://fantasy.premierleague.com/api/leagues-classic/{id}/standings/';
    const FANTASY_H2H_LEAGUE_URL = 'https://fantasy.premierleague.com/api/leagues-h2h-standings/';
    const FANTASY_LEAGUES_URL = 'https://fantasy.premierleague.com/api/leagues-entered/';

    const POSITION_KEEPER = 1;
    const POSITION_DEFENDER = 2;
    const POSITION_MIDFIELDER = 3;
    const POSITION_FORWARD = 4;

    const POSITIONS_MINIMUM = [
        self::POSITION_KEEPER => 1,
        self::POSITION_DEFENDER => 3,
        self::POSITION_MIDFIELDER => 2,
        self::POSITION_FORWARD => 1,
    ];

    /**
     * The chosen team's numeric ID
     *
     * @var integer
     */
    protected $teamId;

    /**
     * The URL from which to get info about chosen team
     *
     * @var string
     */
    protected $teamURL;

    /**
     * The URL from which to get info about chosen team's leagues
     *
     * @var string
     */
    protected $leaguesURL;

    protected $staticData;

    protected $playerData;

    protected $curl;

    public $errorMessage;

    public function __construct()
    {
        $this->curl = new Curler();
        $this->setStaticData();
        $this->setPlayerData();
    }

    public function isUpdating()
    {
        $updating = $this->curl->get('https://fantasy.premierleague.com/api/me/', true);
        if ($updating == 'The game is being updated.') {
            return true;
        }

        return false;
    }

    public function isActive()
    {
        $active = false;
        $i = 0;
        while(!$active && $i < 37) {
            $event = $this->staticData['events'][$i];
            if ($event['is_current'] && !$event['finished']) {
                $active = true;
            }
            $i++;
        }

        return $active;
    }

    public function isCountingDown()
    {
        if ($this->isActive() || $this->isUpdating()) {
            return 0;
        }

        $gameweek = null;
        $i = 0;
        while(!$gameweek && $i < 37) {
            $event = $this->staticData['events'][$i];
            if (strtotime($event['deadline_time']) > time()) {
                $gameweek = $event;
            }
            $i++;
        }

        if (!$gameweek) {
            return 0;
        }

        return strtotime($event['deadline_time']) + 0 * 60;
    }

    public function setStaticData()
    {
        if (!empty($this->staticData)) {
            return true;
        }

        $this->staticData = $this->curl->get(self::FANTASY_STATIC_DATA_URL, true);
        return true;
    }

    public function setPlayerData()
    {
        if (!empty($this->playerData)) {
            return true;
        }

        $data = $this->staticData;
        if (!$data) {
            $data = $this->curl->get(self::FANTASY_STATIC_DATA_URL, true);
        }

        if (!$data) {
            $this->playerData = [];
            return true;
        }

        $players = [];
        foreach ($data['elements'] as $player) {
            $players[$player['id']] = [
                'name' => $player['web_name'],
                'position' => $player['element_type'],
                'first_name' => $player['first_name'],
                'second_name' => $player['second_name'],
                'web_name' => $player['web_name'],
                'team' => $player['team'],
                'cost' => number_format(($player['now_cost'] / 10), 1),
                'availability' => $player['chance_of_playing_this_round'],
                'availability_text' => $player['news'],
            ];
        }

        $this->playerData = $players;
        return true;
    }

    public function setTeam($teamId)
    {
        $this->teamId = $teamId;
        $this->teamURL = self::FANTASY_TEAM_URL . $teamId .'/';
        $this->leaguesURL = self::FANTASY_LEAGUES_URL . $teamId;
    }

    public function getTeamData($teamId)
    {
        $this->setTeam($teamId);
        return $this->curl->get($this->teamURL, true);
    }

    public function getLeagueData($leagueId, $page = 1)
    {
        // For every team in the given league, generate URLs from which to get individual team's points data
        $leagueURLs = [];
        if (is_numeric($page)) {
            // Fetch specific page (lazy loading)
            $leagueURLs[] = str_replace('{id}', $leagueId, self::FANTASY_CLASSIC_LEAGUE_URL) . '?phase=1&page_new_entries=1&page_standings=' . $page;
        } else {
            // Fetch a set of pages
            for ($i = 1; $i <= count($page); $i++) {
                $leagueURLs[] = str_replace('{id}', $leagueId, self::FANTASY_CLASSIC_LEAGUE_URL) . '?phase=1&page_new_entries=1&page_standings=' . $i;
            }
        }

        $leagueData = $this->curl->getMulti($leagueURLs, true);

        $currentEvent = false;
        $teams = [];
        foreach ($leagueData as $page) {
            foreach ($page['standings']['results'] as $index => $team) {
                // Get current gameweek
                if (!$currentEvent) {
                    $eventTeam = $this->getTeamData($team['entry']);
                    $currentEvent = $eventTeam['current_event'];
                }
                $teams[$team['entry']] = [
                    'entry' => $team['entry'],
                    'team_name' => $team['entry_name'],
                    'league' => $leagueId,
                    'player_name' => $team['player_name'],
                    'total' => $team['total'],
                    'is_last' => !$page['standings']['has_next'] && $index == (count($page['standings']['results'])-1),
                    'url' => "https://fantasy.premierleague.com/api/entry/{$team['entry']}/event/$currentEvent/picks/",
                    'last_rank' => $team['last_rank'],
                    'real_event_total' => 0,
                    'event_total' => $team['event_total'],
                    'real_total' => $team['total'] - $team['event_total'],
                    'expanded' => false,
                ];
            }
        }

        // Perform fetch of gameweek data for each team in chosen league
        $gameweekTeamData = $this->getGameweekDataForTeamsInLeague($teams);
        // Perform fetch of live gameweek information about player points and fixtures
        $gameweekPointsData = $this->curl->get("https://fantasy.premierleague.com/api/fixtures/?event=$currentEvent", true);
        $gameweekLiveData = $this->curl->get("https://fantasy.premierleague.com/api/event/$currentEvent/live/", true);
        // Perform fetch of gameweek fixture schedule data
        $gameweekFixturesData = $this->getFixtureData($gameweekPointsData);
        // Perform fetch of gameweek bonus points
        $gameweekBonusPoints = $this->getBonusPointsData($gameweekPointsData);
        // Perform fetch of gameweek transfers made by each team in chosen league
        $gameweekTransfersData = $this->getTransfersData($teams, $currentEvent);

        // Loop through each team's player picks
        foreach ($gameweekTeamData as $teamId => $team) {
            $picks = $team['picks'];
            $playingPositions = [];
            $autoSubbableOut = [];
            $autoSubbableIn = [];
            // Add some additional data about each player in currently iterated team
            foreach ($picks as $playerNumber => $player) {
                $id = $player['element'];
                $benched = $playerNumber > 10 && $team['active_chip'] != 'bboost';
                // Set multiplier to 1 for benched players with multiplier 0
                $realMultiplier = $player['multiplier'] == 0 ? 1 : $player['multiplier'];
                $picks[$playerNumber] = [
                    'id' => $id,
                    'firstName' => $this->playerData[$id]['first_name'],
                    'secondName' => $this->playerData[$id]['second_name'],
                    'name' => $this->playerData[$id]['web_name'],
                    'fullName' => $this->playerData[$id]['first_name'] . ' ' . $this->playerData[$id]['second_name'],
                    'cost' => $this->playerData[$id]['cost'],
                    'position' => $this->playerData[$id]['position'],
                    'multiplier' => $player['multiplier'],
                    'tripleCaptain' => $player['multiplier'] == 3,
                    'captain' => $player['is_captain'],
                    'viceCaptain' => $player['is_vice_captain'],
                    'availability' => $this->playerData[$id]['availability'],
                    'availability_text' => $this->playerData[$id]['availability_text'],
                    'points' => 0,
                    'bonus' => 0,
                    'bonus_provisional' => false,
                    'transferred_in_for' => false,
                    'benched' => $benched,
                    'breakdown' => $this->getLivePointsDataForPlayers($gameweekLiveData['elements'], $id, $currentEvent),
                    'fixtures' => $gameweekFixturesData[$this->playerData[$id]['team']],
                ];

                // Insert who this player was transferred in for if he was transferred in this gameweek
                if (isset($gameweekTransfersData[$teamId])) {
                    foreach ($gameweekTransfersData[$teamId] as $teamTransfers) {
                        foreach ($teamTransfers as $in => $out) {
                            if ($id == $in) {
                                $picks[$playerNumber]['transferred_in_for'] = $this->playerData[$out]['web_name'];
                            }
                        }
                    }
                }

                $pos = $picks[$playerNumber]['position'];
                if (!$benched) {
                    $playingPositions[$pos] = isset($playingPositions[$pos]) ? $playingPositions[$pos]+1 : 1;
                }

                list($eligibleAutoSubOut, $eligibleAutoSubIn) = $this->getAutoSubStatus($picks[$playerNumber]);
                if ($eligibleAutoSubOut) {
                    $autoSubbableOut[$playerNumber] = $pos;
                }
                if ($eligibleAutoSubIn) {
                    $autoSubbableIn[$playerNumber] = $pos;
                }

                // Determine this player's current points, doubled or tripled if captained or triple captained
                // Add these points to team's and individual tally
                $playerPoints = $picks[$playerNumber]['breakdown']['stats']['total_points'] * $realMultiplier;
                if (!$benched) {
                    $gameweekTeamData[$teamId]['real_event_total'] += $playerPoints;
                }
                $picks[$playerNumber]['points'] = $playerPoints;

                // No bonus points for this player, carry on
                if (!isset($gameweekBonusPoints[$id])) {
                    continue;
                }

                // Add bonus points
                $picks[$playerNumber]['bonus'] = $gameweekBonusPoints[$id]['points'];
                $picks[$playerNumber]['bonus_provisional'] = false;

                // These bonus points are confirmed, no need to add to team and individual tally
                if ($gameweekBonusPoints[$id]['confirmed']) {
                    continue;
                }

                $playerBonusPoints = $gameweekBonusPoints[$id]['points'] * $realMultiplier;
                // Update status to reflect that bonus points are unconfirmed
                $picks[$playerNumber]['bonus_provisional'] = true;
                // Add this player's current bonus points to team's and individual tally
                $gameweekTeamData[$teamId]['real_event_total'] += !$benched ? $playerBonusPoints : 0;
                $picks[$playerNumber]['points'] += $playerBonusPoints;
            }

            $gameweekTeamData[$teamId]['playing_positions'] = $playingPositions;
            $gameweekTeamData[$teamId]['autosubbable_out'] = $autoSubbableOut;
            $gameweekTeamData[$teamId]['autosubbable_in'] = $autoSubbableIn;

            $gameweekTeamData[$teamId]['real_total'] = $gameweekTeamData[$teamId]['real_total'] + $gameweekTeamData[$teamId]['real_event_total'];
            $gameweekTeamData[$teamId]['picks'] = $picks;
            if (!empty($autoSubbableOut) && !empty($autoSubbableIn)) {
                $this->performAutoSubs($gameweekTeamData[$teamId]);
            }
        }

        return array_values($gameweekTeamData);
    }

    protected function performAutoSubs(&$teamData)
    {
        $subbedIn = [];
        foreach ($teamData['autosubbable_out'] as $number => $position) {
            // Ensure we show correctly in rare cases where game has performed automatic substitutions
            if ($teamData['picks'][$number]['benched']) {
                continue;
            }
            // Check if an autosub would take team under minimum number of players for outgoing player's position
            $canSubForAnyone = ($teamData['playing_positions'][$position]-1) >= self::POSITIONS_MINIMUM[$position];
            foreach ($teamData['autosubbable_in'] as $inNumber => $inPosition) {
                // Prevent keepers from subbing in for other positions
                if ($inPosition == self::POSITION_KEEPER && $position != self::POSITION_KEEPER) {
                    continue;
                }
                // If player going out can be subbed for anyone, or players play same position, perform autosub
                if ($canSubForAnyone || $position == $inPosition) {
                    // Player already autosubbed in
                    if (in_array($inNumber, $subbedIn)) {
                        continue;
                    }
                    $teamData['picks'][$number]['benched'] = true;
                    $teamData['picks'][$inNumber]['benched'] = false;
                    $teamData['real_event_total'] += $teamData['picks'][$inNumber]['points'];
                    $teamData['real_total'] += $teamData['picks'][$inNumber]['points'];
                    $subbedIn[] = $inNumber;
                    break;
                }
            }
        }
    }

    private function getGameweekDataForTeamsInLeague($teams)
    {
        $urls = [];
        foreach ($teams as $team) {
            $urls[$team['entry']] = $team['url'];
        }

        $gameweekTeamData = $this->curl->getMulti($urls, true);
        foreach ($gameweekTeamData as $id => $gameweekTeam) {
            $teams[$id] = array_merge($teams[$id], [
                'event_transfers' => $gameweekTeam['entry_history']['event_transfers'],
                'event_transfers_cost' => $gameweekTeam['entry_history']['event_transfers_cost'],
                'picks' => $gameweekTeam['picks'],
                'active_chip' => $gameweekTeam['active_chip']
            ]);
        }

        return $teams;
    }

    protected function getLivePointsDataForPlayers($players, $id, $currentEvent)
    {
        foreach ($players as $player) {
            if ($player['id'] == $id) {
                return $player;
            }
        }

        return false;
    }

    /**
     * Based on given gameweek data, returns an array of fixtures,
     * each containing fixture status data
     *
     * The return array is keyed by team ID
     *
     * @param  array $fixtures
     * @return array
     */
    private function getFixtureData($fixtures)
    {
        $teamFixtures = [];
        $now = new DateTime('now');
        foreach ($fixtures as $fixture) {
            $timeUntilKickoff = $now->diff(new DateTime($fixture['kickoff_time']))->format('%dd %hh %im');
            while (substr($timeUntilKickoff, 0, 1) === '0') {
                $timeUntilKickoff = substr($timeUntilKickoff, 3);
            }
            $fixtureData = [
                'finished' => $fixture['finished_provisional'],
                'started' => $fixture['started'],
                'minutes' => $fixture['minutes'],
                'time_until_kickoff' => $timeUntilKickoff
            ];

            $teamFixtures[$fixture['team_h']][] = $fixtureData;
            $teamFixtures[$fixture['team_a']][] = $fixtureData;
        }

        return $teamFixtures;
    }

    /**
     * Based on given gameweek data, determines which players are on for bonus points
     *
     * @param  array $gameweekPointsData Information about the current gameweek
     * @return array
     */
    private function getBonusPointsData($fixtures)
    {
        $bonusPoints = [];
        foreach ($fixtures as $fixture) {
            // Game hasn't started, carry on
            if (!$fixture['started']) {
                continue;
            }

            // Determine if bonus points are confirmed or not, finding the bonus data in the process
            if (empty($fixture['stats'][8]['a']) && empty($fixture['stats'][8]['h'])) {
                $confirmed = false;
                $bonusData = $fixture['stats'][9];
            } else {
                $confirmed = true;
                $bonusData = $fixture['stats'][8];
            }

            // Merge home and away team BPS data
            $combined = array_merge($bonusData['a'], $bonusData['h']);
            // Sort the combined list desc by value
            usort($combined, function($a, $b) {
                return $b['value'] <=> $a['value'];
            });

            // Add appropriate bonus points to top three
            // players in combined bonus points list
            for ($i = 0; $i <= 2; $i++) {
                $lookup = [3, 2, 1]; // Lookup number of points based on position in list
                // Add points and confirmation status, key by player ID
                $bonusPoints[$combined[$i]['element']] = [
                    'points' => $lookup[$i],
                    'confirmed' => $confirmed,
                ];
            }
        }

        return $bonusPoints;
    }

    protected function getTransfersData($teams, $currentEvent)
    {
        $urls = [];
        foreach ($teams as $id => $team) {
            $urls[] = self::FANTASY_TEAM_URL . $id . '/transfers/';
        }

        $transferData = $this->curl->getMulti($urls, true);
        $transfers = [];
        foreach ($transferData as $key => $team) {
            if (empty($team)) {
                continue;
            }

            foreach ($team as $transfer) {
                if ($currentEvent > $transfer['event']) {
                    break;
                }

                $transfers[$transfer['entry']][] = [$transfer['element_in'] => $transfer['element_out']];
            }
        }

        return $transfers;
    }

    /**
     * Determine whether the given pick is eligible for an
     * automatical substitution in our out
     *
     * @param  array $pick
     * @return array Consists of two values, whether player can be subbed out or in respectively
     */
    protected function getAutoSubStatus($pick)
    {
        $out = true;
        $in = true;

        // Determine if pick's team has played yet
        $picksTeamHasPlayed = false;
        foreach ($pick['fixtures'] as $fixture) {
            if ($fixture['started']) {
                $picksTeamHasPlayed = true;
            }
        }

        $minutes = $pick['breakdown']['stats']['minutes'];
        // Pick has played in the GW, cannot be subbed out
        if ($minutes) {
            $out = false;
        } else if (!$picksTeamHasPlayed) {
            // Pick's team hasn't played yet, so a bit early to sub him out
            $out = false;
        } else if ($picksTeamHasPlayed) {
            // Pick hasn't played and thus can't enter from bench
            $in = false;
        }
        // Pick is in starting XI, cannot be subbed in from bench
        if (!$pick['benched']) {
            $in = false;
        }

        return [$out, $in];
    }

    protected function dump($data, $die = false)
    {
        echo '<pre>';var_dump($data);echo '</pre>';
        if ($die) {
            die();
        }
    }
}
