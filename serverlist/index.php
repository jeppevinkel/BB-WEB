<?php
date_default_timezone_set('CET');
header('Content-Type: application/json');

include '../secrets/mysql-secrets.php';

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

$servers = array();

$query = $mysqli->prepare("SELECT * FROM servers");
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

//var_dump($servers);
echo json_encode($servers);

?>