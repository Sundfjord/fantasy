<?php
	require_once(__DIR__.'/curler.php');

	$team = (int)$_GET['team'] ? $_GET['team'] : null;
	$teamData = null;

	$league = (int)$_GET['league'] ? $_GET['league'] : null;
	$leagueData = null;

	$curler = new Curler($team);
	$static = $curler->getStatic();
	if ($team) {
		$teamData = $curler->getTeamData();
	}
	if ($league) {
		$leagueData = $curler->getLeagueData($league);
	}

	$playerData = $curler->getPlayerData();

	$currentGWData;
	$nextGWData;
	$updating = true;
	if (!empty($static)) {
		$updating = false;
		foreach ($static['events'] as $gw => $gwData) {
			if ($gwData['is_current']) {
				$currentGWData = $gwData;
				$nextGWData = $static['events'][$gw+1];
				break;
			}
		}
	}

	$timestamp = 0;
	if ($currentGWData['finished']) {
		$timestamp = strtotime($nextGWData['deadline_time']) + 60 * 60;
	}
	$compatible = true;
	$userAgent = $_SERVER['HTTP_USER_AGENT'];
	$unsupported = [
		'SamsungBrowser',
		'UCBrowser',
		'MSIE',
		'Trident'
	];
	foreach ($unsupported as $string) {
		if (strpos($userAgent, $string) !== false) {
			$compatible = false;
			break;
		}
	}
?>

<!DOCTYPE html>
<!-- [if IE 8]><html class="ie8" lang="en"><![endif] -->
<!-- [if IE 9]><html class="ie9" lang="en"><![endif] -->
<!--[if !IE]> -->
<html lang="en">
    <head>
    	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0" />
        <title>Fantasy Checker</title>
        <link rel="stylesheet" type="text/css" href="/fantasy/css/siimple.min.css" />
        <link rel="stylesheet" type="text/css" href="/fantasy/css/style.css" />
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">
        <script src="https://unpkg.com/tippy.js@3/dist/tippy.all.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.2.1.js" integrity="sha256-DZAnKJ/6XZ9si04Hgrsxu/8s717jcIzLy3oi35EouyE=" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.5.3/vue.js"></script>
        <!-- <script src="/fantasy/elezewbthpjgyy.php"></script> -->
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-124543902-1"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', 'UA-124543902-1');
        </script>

    </head>
	<body>
		<div class="siimple-jumbotron siimple-jumbotron--extra-large fantasy">
		    <div class="siimple-jumbotron-title">
		    	<a class="fantasy" href="/fantasy">
		    		Fantasy <span class="siimple-grid-col--xs-hide siimple-grid-col--sm-hide siimple-grid-col--md-hide">Premier League</span> Checker
		    	</a>
		    </div>
		    <div class="siimple-jumbotron-detail">
				When you simply cannot wait for your minileague to be updated.
		    </div>
		</div>
		<?php if ($compatible) {
			if ($updating) { ?>
				<div class="siimple-content siimple-content--extra-large">
					<div class="siimple-grid">
						<div class="siimple-grid-col--12">
							<div class="siimple-box siimple-box-icon">
								<i class="fas fa-sync-alt siimple-warning"></i>
							    <div class="siimple-box-title">The game is updating.</div>
							    <div class="siimple-box-subtitle">Please try again in a moment.</div>
							</div>
						</div>
					</div>
				</div>
			<?php } else { ?>
				<div id="app" class="siimple-content siimple-content--extra-large">
					<main-component
						:timestamp="<?php echo $timestamp; ?>"
						:playerdata='<?php echo json_encode($playerData) ?>'
						:submittedteam='<?php echo json_encode($teamData) ?>'
						:submittedleague='<?php echo json_encode($leagueData) ?>'>
					</main-component>
				</div>
				<script type="module" src="/fantasy/js/App.js?v=<?php echo str_shuffle('abdefghijklmnopqrstuxyz1234567890') ?>"></script>
				<script>
				    $(document).on("click", '.open-modal', function () {
				        document.getElementById("modal").style.display = "";
				    });
				    $(document).on("click", '#modal-close', function() {
				    	document.getElementById("modal").style.display = "none";
				    });
				</script>
			<?php } ?>
		<?php } else { ?>
			<div class="siimple-content siimple-content--extra-large">
				<div class="siimple-grid">
					<div class="siimple-grid-col--12">
						<div class="siimple-box siimple-box-icon">
							<i class="fas fa-exclamation-triangle siimple-warning"></i>
						    <div class="siimple-box-title">Your browser is not supported</div>
						    <div class="siimple-box-subtitle">Try again with a non-shit browser, like Chrome, FireFox, Safari or Edge.</div>
						</div>
					</div>
				</div>
			</div>
		<?php } ?>
		<div class="siimple-footer siimple-footer-sticky" align="center">
		    Laga av <strong>sundfjord</strong>
		</div>
	</body>
</html>