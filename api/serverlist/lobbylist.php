<?php
date_default_timezone_set('CET');
header('Content-Type: application/json');

include '../../secrets/mysql-secrets.php';

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

$servers = array();

$query = $mysqli->prepare("SELECT * FROM servers WHERE last_request >= NOW() - INTERVAL 2 MINUTE");
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
	$server['privateBeta'] = (bool)$row['private_beta'];
	$server['friendlyFire'] = (bool)$row['friendly_fire'];
	$server['modded'] = (bool)$row['modded'];
	$server['whitelist'] = (bool)$row['whitelist'];
	$server['official'] = "GLOBAL OFFICIAL";
	$server['staffRA'] = (bool)$row['staff_ra'];
	$server['geoblocking'] = (bool)$row['geoblocking'];
	$server['accessRestrictions'] = (bool)$row['access_restrictions'];
	$server['emailSet'] = (bool)$row['email_set'];
	$server['playerlist'] = $row['playerlist'];
	$server['lastUpdate'] = $row['last_request'];

	array_push($servers, $server);
}

//var_dump($servers);
$serversAssociative = array('servers' => $servers);

$jsonOut = json_encode($serversAssociative);

if ($_GET['format'] == "json-signed-unix" && $_GET['version'] == 2 && $_GET['minimal'] == 1) {
	$arr = array();

	$arr['payload'] = base64_encode($jsonOut);
	$arr['timestamp'] = time();
	$arr['signature'] = "MIGIAkIB1gRUBp8cl+ND5jpc1rirtgDUAClrpN4RhE7xapzaeluW4r+DyY0hgzB3Pg5dJe6KCnB03YT+8OFuWqClsO4Ps2cCQgF61OrfJeo7CA1uvGAPOs/i4srj/py6nb5CO7S3flD3KRi80FSCI8CHSTc+gKiQvcS5EVP0JG43Np9awvww3ovGmg==";

	echo json_encode($arr);
	exit();
}

echo $jsonOut;

?>