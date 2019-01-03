<?php

require_once(__DIR__.'/Curler.php');

/**
 * Class to perform multiCurl queries
 */
class FantasyData
{
    const FANTASY_STATIC_DATA_URL = 'https://fantasy.premierleague.com/drf/bootstrap-static';
    const FANTASY_TEAM_URL = 'https://fantasy.premierleague.com/drf/entry/';
    const FANTASY_CLASSIC_LEAGUE_URL = 'https://fantasy.premierleague.com/drf/leagues-classic-standings/';
    const FANTASY_H2H_LEAGUE_URL = 'https://fantasy.premierleague.com/drf/leagues-h2h-standings/';
    const FANTASY_LEAGUES_URL = 'https://fantasy.premierleague.com/drf/leagues-entered/';

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
        if (empty($this->staticData)) {
            return true;
        }

        return false;
    }

    public function getTimeToNextGameweek()
    {
        $currentGWData;
        $nextGWData;
        if ($this->isUpdating()) {
            return 0;
        }

        foreach ($this->staticData['events'] as $gw => $gwData) {
            if (!$gwData['is_current']) {
                continue;
            }

            $currentGWData = $gwData;
            $nextGWData = $this->staticData['events'][$gw+1];
        }

        $countdown = 0;
        if ($currentGWData['finished']) {
            $countdown = strtotime($nextGWData['deadline_time']) + 60 * 60;
        }

        return $countdown;
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

        $data = $this->curl->get('https://fantasy.premierleague.com/drf/bootstrap-static', true);
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
                'cost' => number_format(($player['now_cost'] / 10), 1)
            ];
        }

        $this->playerData = $players;
        return true;
    }

    public function setTeam($teamId)
    {
        $this->teamId = $teamId;
        $this->teamURL = self::FANTASY_TEAM_URL . $teamId;
        $this->leaguesURL = self::FANTASY_LEAGUES_URL . $teamId;
    }

    public function getTeamData($teamId)
    {
        $this->setTeam($teamId);
        return $this->curl->get($this->teamURL, true);
    }

    public function getLeagueData($leagueId)
    {
        // Get current gameweek
        $currentEvent = $this->staticData['current-event'];

        // Fetch the live points for fixtures and players
        $pointsData = $this->curl->get("https://fantasy.premierleague.com/drf/event/$currentEvent/live", true);

        // For every team in the given league, generate URLs from which to get individual team's points data
        $leagueData = $this->curl->get(self::FANTASY_CLASSIC_LEAGUE_URL . $leagueId, true);
        $urls = [];
        foreach ($leagueData['standings']['results'] as $index => $team) {
            $urls[] = "https://fantasy.premierleague.com/drf/entry/". $team["entry"] . "/event/$currentEvent/picks";
        }
        // Perform fetch of team points data
        $teamData = $this->curl->getMulti($urls, true);

        // Loop through each team's player picks
        $teamResults = [];
        foreach ($teamData as $index => $team) {
            $playerData = [];
            // Add some additional data about each player in currently iterated team
            foreach ($team['picks'] as $key => $player) {
                // Only calculate active players (not benched), unless Bench Boost is active
                if ($key >= 11 && $team['active_chip'] != 'bboost') {
                    break;
                }
                $playerId = $player['element'];
                $playerData[$playerId] = [
                    'id' => $playerId,
                    'firstName' => $this->playerData[$playerId]['first_name'],
                    'secondName' => $this->playerData[$playerId]['second_name'],
                    'name' => $this->playerData[$playerId]['web_name'],
                    'fullName' => $this->playerData[$playerId]['first_name'] . ' ' . $this->playerData[$playerId]['second_name'],
                    'cost' => $this->playerData[$playerId]['cost'],
                    'position' => $this->playerData[$playerId]['position'],
                    'multiplier' => $player['multiplier'],
                    'activeCaptain' => $player['multiplier'] > 1,
                    'tripleCaptain' => $player['multiplier'] == 3,
                    'captain' => $player['is_captain'],
                    'viceCaptain' => $player['is_vice_captain'],
                    'points' => 0,
                    'bonus' => 0,
                    'bonus_provisional' => false,
                    'breakdown' => $pointsData['elements'][$playerId]['explain'][0][0]
                ];
            }

            $teamPoints = 0;
            $bonusPointsData = $this->getBonusPointsData($pointsData, array_keys($playerData));
            foreach ($playerData as $id => $data) {
                $player = $playerData[$id];
                // Determine this player's current points, doubled or tripled if appropriate
                $playerPoints = $pointsData['elements'][$id]['stats']['total_points'] * $data['multiplier'];

                // Add this player's current points to team's and individual tally
                $teamPoints += $playerPoints;
                $playerData[$id]['points'] = $playerPoints;

                // No bonus points for this player, carry on
                if (!isset($bonusPointsData[$id])) {
                    continue;
                }

                // Add bonus points
                $player['bonus'] = $bonusPointsData[$id]['points'];
                $player['bonus_provisional'] = false;
                // If bonus points are provisional, add to team and individual tally
                if (!$bonusPointsData[$id]['confirmed']) {
                    $playerBonusPoints = $bonusPointsData[$id]['points'] * $data['multiplier'];
                    // Update status to reflect that bonus points are unconfirmed
                    $player['bonus_provisional'] = true;
                    // Add this player's current bonus points to team's and individual tally
                    $teamPoints += $playerBonusPoints;
                    $player['points'] += $playerBonusPoints;
                }
            }

            $teamResults[$index]['points'] = $teamPoints;
            $teamResults[$index]['picks'] = array_values($playerData);
            $teamResults[$index]['chip'] = $team['active_chip'];
        }

        $results = [];
        foreach ($leagueData['standings']['results'] as $index => $team) {
            $team['real_event_total'] = $teamResults[$index]['points'];
            $team['real_total'] = ($team['total'] - $team['event_total'] + $team['real_event_total']);
            $team['picks'] = $teamResults[$index]['picks'];
            $team['chip'] = $teamResults[$index]['chip'];
            $team['expanded'] = false;
            $results[] = $team;
        }

        return $results;
    }

    /**
     * Based on given gameweek data, determines which players are on for bonus points
     *
     * @param  array $pointsData Information about the current gameweek
     * @return array
     */
    private function getBonusPointsData($pointsData)
    {
        $bonusPoints = [];
        foreach ($pointsData['fixtures'] as $fixture) {
            // Game hasn't started, carry on
            if (!$fixture['started']) {
                continue;
            }

            // Determine if bonus points are confirmed or not, finding the bonus data in the process
            if (empty($fixture['stats'][8]['bonus']['a']) && empty($fixture['stats'][8]['bonus']['h'])) {
                $confirmed = false;
                $bonusData = $fixture['stats'][9]['bps'];
            } else {
                $confirmed = true;
                $bonusData = $fixture['stats'][8]['bonus'];
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

    protected function pretty_dump($data)
    {
        echo '<pre>';var_dump($data);echo '</pre>';
    }
}
