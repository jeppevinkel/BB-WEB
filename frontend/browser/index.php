<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('CET');

$startTime = microtime(true) * 1000;

require '../../vendor/autoload.php';

include '../../secrets/mysql-secrets.php';

use \Phlib\XssSanitizer\Sanitizer;
$sanitizer = new Sanitizer();

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

$servers = array();

$query = $mysqli->prepare("SELECT * FROM servers WHERE last_request >= NOW() - INTERVAL 2 MINUTE ORDER BY players DESC, address DESC, connection_port ASC");
$query->execute();
$result = $query->get_result();

while ($row = $result->fetch_assoc()) {
	$server = array();

	$server['serverId'] = $row['id'];
	$server['accountId'] = null;
	$server['ip'] = $row['address'];
	$server['port'] = $row['connection_port'];
	$server['players'] = $row['players'];
	$server['distance'] = 0;
	$server['info'] = $row['info'];
	$server['pastebin'] = $row['pastebin'];
	$server['version'] = $row['server_version'];
	$server['privateBeta'] = $row['private_beta'];
	$server['friendlyFire'] = $row['friendly_fire'];
	$server['modded'] = $row['modded'];
	$server['whitelist'] = $row['whitelist'];
	$server['official'] = "REGIONAL OFFICIAL";
	$server['staffRA'] = $row['staff_ra'];
	$server['geoblocking'] = $row['geoblocking'];
	$server['accessRestrictions'] = $row['access_restrictions'];
	$server['emailSet'] = $row['email_set'];
	$server['enforceSameIp'] = $row['enforce_same_ip'];
	$server['enforceSameAsn'] = $row['enforce_same_asn'];
	$server['playerlist'] = $row['playerlist'];
	$server['lastUpdate'] = $row['last_request'];

	array_push($servers, $server);
}

?>

<!DOCTYPE html>
<html>
<head>
	<title>Southwood Servers</title>
	<meta name="description" content="Southwood Studios's SCP: Secret Laboratory Server Browser">
	<meta name="keywords" content="southwood,scpsl,serverlist,server,list,browser,scp,secret,laboratory">

	<link rel="stylesheet" type="text/css" href="stylesheet.css">
</head>
<body>
	<div class="projector"></div>
	<div class="header">
		<img src="scpsl.png" class="logo">
	</div>
	<div class="server-list">
<?php
$totalPlayers = 0;
$exiledCount = 0;
if (isset($servers)) {
	for ($i = 0; $i < count($servers); $i++){
		$serverName = ConvertUnityText(base64_decode($servers[$i]['info']));
		if (strpos($serverName, 'EXILED') !== false) {
			$exiledCount++;
		}
		echo '<div class="server">';
		echo '	<div class="server-info">';
		echo '		' . (new Sanitizer())->sanitize($servers[$i]['players']) . ' <a href="https://pastebin.com/' . (new Sanitizer())->sanitize($servers[$i]['pastebin']) . '">INFO</a>';
		echo '	</div>';
		echo '	<div class="server-name-container">';
		echo '		<div class="server-name">';
		echo '			' . $serverName;
		echo '		</div>';
		echo '	</div>';
		echo '	<div class="server-address">';
		echo '		' . $servers[$i]['ip'] . ':' . $servers[$i]['port'];
		echo '	</div>';
		echo '</div>';
		$totalPlayers += intval(explode('/', $servers[$i]['players'])[0]);
	}
}
?>
	</div>
	<div class="footer">
		Total Servers: <?php echo count($servers); ?> - Total Players: <?php echo $totalPlayers; ?> - Total EXILED Servers: <?php echo $exiledCount; ?> - SCP:SL Server Browser by Southwood <!-- - <a href="https://southwoodstudios.com/browser/?table=y">View As Table</a>  -->- Time to Sort: <?php echo round((microtime(true) * 1000) - $startTime, 2); ?> ms
	</div>
</body>
</body>
</html>

<?php

function ConvertUnityText($str){
	$newstr = preg_replace_callback('/<color=(.*?)>(.*?)<\/color>/', function($m){
		return '<span style="color:' . hex2rgba($m[1]) . ';color:' . hex2rgba($m[1], true) . ';">' . $m[2] . '</span>';
	},$str);

	$newstr = preg_replace_callback('/<size=(.*?)>(.*?)<\/size>/', function($m){
		$out = '<span id="unity-size" style="font-size:' . floatval($m[1])*2.8 . '%;">' . $m[2] . '</span>';
		return $out;
	}, $newstr);
	// $newstr = $sanitizer->sanitize($newstr);
	$newstr = (new Sanitizer())->sanitize($newstr);
	return $newstr;
}

function hex2rgba($color, $alpha = false) {

	$default = 'rgb(255,255,255)';
 
	//Return default if no color provided
	if(empty($color))
          return $default; 
 
        if ($color[0] == '#' ) {
        	$color = substr( $color, 1 );
        } else {
        	return $color;
        }
        $a = "ff";
 		
 		switch (strlen($color)) {
 			case '8':
 				$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
 				$a = $color[6] . $color[7];
 				break;
 			case '6':
 				$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
 				break;
 			case '4':
 				$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
 				$a = $color[3] . $color[3];
 				break;
 			case '3':
 				$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
 				break;
 			default:
 				return $default;
 				break;
 		}
 
        //Convert hexadec to rgb
        $rgb =  array_map('hexdec', $hex);
 
        //Check if opacity is set(rgba or rgb)
        if($alpha){
        	$output = 'rgba('.implode(",",$rgb).','.map(hexdec($a), 0, 255, 0.0, 1.0).')';
        } else {
        	$output = 'rgb('.implode(",",$rgb).')';
        }
 
        //Return rgb(a) color string
        return $output;
}

function map($value, $fromLow, $fromHigh, $toLow, $toHigh) {
    $fromRange = $fromHigh - $fromLow;
    $toRange = $toHigh - $toLow;
    $scaleFactor = $toRange / $fromRange;

    // Re-zero the value within the from range
    $tmpValue = $value - $fromLow;
    // Rescale the value to the to range
    $tmpValue *= $scaleFactor;
    // Re-zero back to the to range
    return $tmpValue + $toLow;
}

?>