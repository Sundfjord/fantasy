<?php
	require_once(__DIR__.'/Base.php');
	require_once(__DIR__.'/FantasyData.php');
	$base = new Base();
	$fantasy = new FantasyData();

	$data = ['baseURL' => $_SERVER['SERVER_NAME']];
	// Turn off error reporting in live environment
	if (strpos($data['baseURL'], 'sundfjord.com') !== false) {
		error_reporting(0);
	}
	if (isset($_GET['team'])) {
		$data['teamData'] = $fantasy->getTeamData($_GET['team']);
		if (isset($_GET['league'])) {
			$data['leagueData'] = $fantasy->getLeagueData($_GET['league']);
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
        <link rel="stylesheet" type="text/css" href="/fantasy/css/style.css?v=<?php echo str_shuffle('abdefghijklmnopqrstuxyz1234567890') ?>" />
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
        <script>
			var preloaded = <?php echo json_encode($data); ?>;
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
		<div id="app" class="siimple-content siimple-content--extra-large">
			<main-component
				:unsupported='<?php echo json_encode($base->visitorIsUsingUnsupportedBrowser()); ?>'
				:updating='<?php echo json_encode($fantasy->isUpdating()); ?>'
				:countdown='<?php echo json_encode($fantasy->getTimeToNextGameweek()); ?>'>
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
		<div class="siimple-footer siimple-footer-sticky" align="center">
		    Laga av <strong>sundfjord</strong>
		</div>
	</body>
</html>