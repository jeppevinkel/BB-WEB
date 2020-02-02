<?php
date_default_timezone_set('CET');
header('Content-Type: application/json');

$startTime = time();

$debugCount = 1;

echo $debugCount++;

include '../' . __DIR__ . '/secrets/mysql-secrets.php';

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);
echo $debugCount++;

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
echo $debugCount++;

$servers = array();

$query = $mysqli->prepare("SELECT * FROM servers");
$query->execute();
$result = $query->get_result();
echo $debugCount++;

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
echo $debugCount++;

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
echo $debugCount++;
	for (int i = 0; count($servers); i++){
		echo '<div class="server">';
		echo '	<div class="server-info">';
		echo '		' . $servers[i]['players'] . ' <a href="https://pastebin.com/' . $servers[i]['pastebin'] . '">INFO</a>';
		echo '	</div>';
		echo '	<div class="server-name-container">';
		echo '		<div class="server-name">';
		echo '			' . base64_decode($servers[i]['info']);
		echo '		</div>';
		echo '	</div>';
		echo '	<div class="server-address">';
		echo '		' . $servers[i]['ip'] . $servers[i]['port'];
		echo '	</div>';
	}
echo $debugCount++;
?>
	</div>
	<div class="footer">
		Total Servers: <?php echo count($servers); ?> - Total Players: ?? - Total ServerMod Servers: ?? - SCP:SL Server Browser by Southwood - <a href="hhttps://southwoodstudios.com/browser
/?table=y">View As Table</a> - Time to Sort: <?php echo time() - $startTime; ?> ms
	</div>
</body>
</body>
</html>