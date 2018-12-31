<?php

/**
 * Class to perform multiCurl queries
 */
class Curler
{
    const FANTASY_ATTEMPTS_LIMIT = 10;

    const FANTASY_STATIC_DATA_URL = 'https://fantasy.premierleague.com/drf/bootstrap-static';
    const FANTASY_TEAM_URL = 'https://fantasy.premierleague.com/drf/entry/';
    const FANTASY_CLASSIC_LEAGUE_URL = 'https://fantasy.premierleague.com/drf/leagues-classic-standings/';
    const FANTASY_H2H_LEAGUE_URL = 'https://fantasy.premierleague.com/drf/leagues-h2h-standings/';
    const FANTASY_LEAGUES_URL = 'https://fantasy.premierleague.com/drf/leagues-entered/';

    /**
     * The current team
     *
     * @var integer
     */
    protected $teamID;

    /**
     * The URL to get info about chosen team
     *
     * @var string
     */
    protected $teamURL;

    /**
     * The URL to get info about chosen team's leagues
     *
     * @var string
     */
    protected $leaguesURL;

    /**asdasd
     * Information about errors occurring during curl requests
     *
     * @var string
     */
    public $errorMessage;

        /**
     * The URL to get info about chosen team's leagues
     *
     * @var string
     */
    protected $debugmode;

    /**
     * Constructor
     *
     * @param User $user [optional] The user to perform API actions as
     */
    public function __construct($teamID = null)
    {
        if ($teamID) {
            $this->teamID = $teamID;
            $this->teamURL = self::FANTASY_TEAM_URL . $teamID;
            $this->leaguesURL = self::FANTASY_LEAGUES_URL . $teamID;
        }

        $this->debugmode = $_SERVER['REMOTE_ADDR'] == '95.34.159.248' || $_SERVER['REMOTE_ADDR'] == '88.89.177.234';
    }

    public function getStatic()
    {
        return $this->get(self::FANTASY_STATIC_DATA_URL, true);
    }

    public function getTeamData()
    {
        return $this->get($this->teamURL, true);
    }

    public function getLeagueData($leagueId)
    {
        // Gets some static data in order to know what the current GW is
        $staticData = $this->getStatic();
        $currentEvent = $staticData['current-event'];

        // Fetch the live points for fixtures and players
        $pointsData = $this->get("https://fantasy.premierleague.com/drf/event/$currentEvent/live", true);

        // For every team in the given league, generate URLs from which to get individual team's points data
        $leagueURL = self::FANTASY_CLASSIC_LEAGUE_URL . $leagueId;
        $leagueData = $this->get($leagueURL, true);
        $urls = [];
        foreach ($leagueData['standings']['results'] as $index => $team) {
            $urls[] = "https://fantasy.premierleague.com/drf/entry/". $team["entry"] . "/event/$currentEvent/picks";
        }
        // Perform fetch of team points data
        $pickResults = $this->getMulti($urls, true);

        // Loop through each team's player picks
        $pickData = [];
        foreach ($pickResults as $index => $picks) {
            $playerData = [];
            $playerIds = [];
            // Add some additional data about each player in currently iterated team
            foreach ($picks['picks'] as $type => $player) {
                $playerIds[] = $player['element'];
                $playerData[] = [
                    'id' => $player['element'],
                    'multiplier' => $player['multiplier'],
                    'activeCaptain' => $player['multiplier'] > 1,
                    'tripleCaptain' => $player['multiplier'] == 3,
                    'captain' => $player['is_captain'],
                    'viceCaptain' => $player['is_vice_captain'],
                    'breakdown' => $pointsData['elements'][$player['element']]['explain'][0][0]
                ];
            }

            $points = 0;
            $playerCount = 0;

            $bonusPointsData = $this->getBonusPointsData($pointsData, $playerIds);
            foreach ($playerData as $key => $data) {
                if ($playerCount == 11 && $picks['active_chip'] != 'bboost') {
                    break;
                }
                $id = $data['id'];

                $points += $pointsData['elements'][$id]['stats']['total_points'] * $data['multiplier'];
                $playerData[$key]['points'] = $pointsData['elements'][$id]['stats']['total_points'] * $data['multiplier'];
                if (isset($bonusPointsData[$id])) {
                    $playerData[$key]['bonus'] = $bonusPointsData[$id]['points'];
                    $playerData[$key]['bonus_provisional'] = false;
                    if (!$bonusPointsData[$id]['added']) {
                        $playerData[$key]['bonus_provisional'] = true;
                        $points += $bonusPointsData[$id]['points'] * $data['multiplier'];
                        $playerData[$key]['points'] += $bonusPointsData[$id]['points'] * $data['multiplier'];
                    }
                }

                if ($data['activeCaptain'] && !$bonusPointsData[$id]['added']) {
                    $points += $bonusPointsData[$id]['points'] * $data['multiplier'];
                }
                $playerCount++;
            }
            $pickData[$index]['points'] = $points;
            $pickData[$index]['picks'] = $playerData;
            $pickData[$index]['chip'] = $picks['active_chip'];
        }

        foreach ($leagueData['standings']['results'] as $index => $team) {
            $team['real_event_total'] = $pickData[$index]['points'];
            $team['picks'] = $pickData[$index]['picks'];
            $team['expanded'] = false;
            $team['chip'] = $pickData[$index]['chip'];
            $leagueData['standings']['results'][$index] = $team;
        }

        return $leagueData['standings']['results'];
    }

    private function getBonusPointsData($pointsData, $playerIds)
    {
        $bonusPoints = [];
        foreach ($pointsData['fixtures'] as $fixture) {
            if (!$fixture['started']) {
                continue;
            }
            if (empty($fixture['stats'][8]['bonus']['a']) && empty($fixture['stats'][8]['bonus']['h'])) {
                $added = false;
                $bonusData = $fixture['stats'][9]['bps'];
            } else {
                $added = true;
                $bonusData = $fixture['stats'][8]['bonus'];
            }

            $combined = array_merge($bonusData['a'], $bonusData['h']);
            usort($combined, function($a, $b) {
                return $b['value'] <=> $a['value'];
            });

            foreach ($combined as $position => $info) {
                if ($position <= 3 && in_array($info['element'], $playerIds)) {
                    $points = 0;
                    switch($position) {
                        case 0:
                            $points = 3;
                            break;
                        case 1:
                            $points = 2;
                            break;
                        case 2:
                            $points = 1;
                            break;
                    }

                    $bonusPoints[$info['element']] = [
                        'points' => $points,
                        'added' => $added,
                    ];
                }
            }
        }

        return $bonusPoints;
    }

    private function pretty_dump($data)
    {
        echo '<pre>';var_dump($data);echo '</pre>';
    }

    public function getPlayerData()
    {
        $data = $this->get('https://fantasy.premierleague.com/drf/bootstrap-static', true);
        $players = [];
        if (!$data) {
            return $players;
        }

        foreach ($data['elements'] as $player) {
            // if ($this->debugmode && $player['id'] == 126) {
            //     $this->pretty_dump($player["first_name"]);
            //     continue;
            // }
            $players[$player['id']] = [
                'name' => $player['web_name'],
                'position' => $player['element_type'],
                // 'first_name' => addslashes($player["first_name"]),
                // 'second_name' => $player['second_name'],
                'cost' => number_format(($player['now_cost'] / 10), 1)

            ];
        }
        return $players;
    }

    public function get($url, $decode = false)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = false;
        $attempts = 1;
        do {
            if (!($result = curl_exec($curl))) {
                $attempts++;
            }
        } while (!$result && $attempts <= self::FANTASY_ATTEMPTS_LIMIT);

        if (!$result) {
            $this->errorMessage = 'Unable to communicate with Fantasy server, please try again later';
            return false;
        }

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode == 404) {
            $this->errorMessage = 'Unable to find team with the given team ID';
            return false;
        }

        curl_close($curl);

        if ($decode) {
            $result = json_decode($result, true);
        }
        return $result;
    }

    public function getMulti(array $urls, $decode = false)
    {
        $multiCurl = curl_multi_init();
        $handles = [];
        foreach ($urls as $url) {
            $curl = curl_init($url);
            $handles[] = $curl;
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($multiCurl, $curl);
        }

        $running = false;
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running > 0);

        $result = [];
        foreach ($handles as $handle) {
            $result[] = curl_multi_getcontent($handle);
            curl_multi_remove_handle($multiCurl, $handle);
        }
        curl_multi_close($multiCurl);

        if ($decode) {
            foreach ($result as $index => $res) {
                $result[$index] = json_decode($res, true);
            }
        }

        return $result;
    }
}
