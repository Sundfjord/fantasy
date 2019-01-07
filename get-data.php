<?php
require_once(__DIR__.'/FantasyData.php');

$data = $_GET;
sleep(1);
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
		$result['data'] = $fantasy->getLeagueData($data['leagueId'], $data['page']);
		break;
}

if (!$result) {
	header('HTTP/1.1 422 Unprocessable Entity');
	echo json_encode(['error' => $fantasy->errorMessage]);
}

$duration = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
$result['duration'] = $duration;//date('u', $duration);

echo json_encode($result);