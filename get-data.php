<?php
require_once(__DIR__.'/curler.php');

$data = $_GET;
sleep(1);
$curler = new Curler($data['teamID']);
switch($data['info']) {
	case 'team':
		$result = $curler->getTeamData();
	break;
	case 'live':
		$result = $curler->getLeagueData($data['leagueId']);
	break;
}

if (!$result) {
	header('HTTP/1.1 422 Unprocessable Entity');
	echo json_encode(['error' => $curler->errorMessage]);
}

echo json_encode($result);