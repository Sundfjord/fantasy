<?php
require_once(__DIR__.'/FantasyData.php');

$data = $_GET;
$fantasy = new FantasyData();

if ($fantasy->isUpdating()) {
	echo json_encode(['updating' => true]);
	return;
}

$result = [];
switch($data['info']) {
	case 'team':
		$result['data'] = $fantasy->getTeamData($data['teamId']);
		break;
	case 'live':
		$count = 0;
		do {
			$result['data'] = $fantasy->getLeagueData($data['leagueId'], $data['page']);
			$count++;
		} while (empty($result['data']) && $count < 5);
		break;
}

if ($fantasy->errorMessage) {
	header('HTTP/1.1 422 Unprocessable Entity');
	$result['error'] = $fantasy->errorMessage;
}

$duration = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
$result['duration'] = $duration;

echo json_encode($result);