<?php
date_default_timezone_set('CET');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../secrets/mysql-secrets.php';

$_errs = array();

if (isset($_GET['ip'])) {
    $_serverAddress = $_GET['ip'];
} else {
    array_push($_errs, "No ip");
}
if (isset($_GET['port'])) {
    $_serverPort = intval($_GET['port']);
} else {
    array_push($_errs, "No port");
}
if (isset($_GET['players'])) {
    $_players = $_GET['players'];
} else {
    array_push($_errs, "No players");
}
if (isset($_GET['enforceSameIp'])) {
    $_enforceSameIp = intval($_GET['enforceSameIp']);
} else {
    array_push($_errs, "No enforceSameIp");
}
if (isset($_GET['enforceSameAsn'])) {
    $_enforceSameAsn = intval($_GET['enforceSameAsn']);
} else {
    array_push($_errs, "No enforceSameAsn");
}
if (count($_errs) >= 1) {
    print_r(json_encode($_errs));
    exit();
}

$_valCount = 0;

if (isset($_GET['info'])) {
    $_info = $_GET['info'];
    $_valCount++;
}
if (isset($_GET['playerList'])) {
    $_playerList = $_GET['playerList'];
    $_valCount++;
}
if (isset($_GET['playerList'])) {
    $_playerList = $_GET['playerList'];
    $_valCount++;
}
if (isset($_GET['pastebin'])) {
    $_pastebin = $_GET['pastebin'];
    $_valCount++;
}
if (isset($_GET['gameVersion'])) {
    $_gameVersion = $_GET['gameVersion'];
    $_valCount++;
}
if (isset($_GET['privateBeta'])) {
    $_privateBeta = intval($_GET['privateBeta']);
    $_valCount++;
}
if (isset($_GET['staffRA'])) {
    $_staffRA = intval($_GET['staffRA']);
    $_valCount++;
}
if (isset($_GET['friendlyFire'])) {
    $_friendlyFire = intval($_GET['friendlyFire']);
    $_valCount++;
}
if (isset($_GET['geoblocking'])) {
    $_geoblocking = intval($_GET['geoblocking']);
    $_valCount++;
}
if (isset($_GET['modded'])) {
    $_modded = intval($_GET['modded']);
    $_valCount++;
}
if (isset($_GET['whitelist'])) {
    $_whitelist = intval($_GET['whitelist']);
    $_valCount++;
}
if (isset($_GET['accessRestrictions'])) {
    $_accessRestrictions = intval($_GET['accessRestrictions']);
    $_valCount++;
}
if (isset($_GET['emailSet'])) {
    $_emailSet = intval($_GET['emailSet']);
    $_valCount++;
}

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);
$mysqli->report_mode = MYSQLI_REPORT_ALL;
mysqli_report(MYSQLI_REPORT_ALL);

if ($_valCount = 13) {
    $_time = date('Y-m-d H:i:s');

    if($query = $mysqli->prepare("
        INSERT INTO servers (address, connection_port, info, pastebin, players, playerlist, server_version, last_request, private_beta, staff_ra, friendly_fire, geoblocking, modded, whitelist, access_restrictions, email_set, enforce_same_ip, enforce_same_asn)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            address = VALUES(address),
            connection_port = VALUES(connection_port),
            info = VALUES(info),
            pastebin = VALUES(pastebin),
            players = VALUES(players),
            playerlist = VALUES(playerlist),
            server_version = VALUES(server_version),
            last_request = VALUES(last_request),
            private_beta = VALUES(private_beta),
            staff_ra = VALUES(staff_ra),
            friendly_fire = VALUES(friendly_fire),
            geoblocking = VALUES(geoblocking),
            modded = VALUES(modded),
            whitelist = VALUES(whitelist),
            access_restrictions = VALUES(access_restrictions),
            email_set = VALUES(email_set),
            enforce_same_ip = VALUES(enforce_same_ip),
            enforce_same_asn = VALUES(enforce_same_asn)"))
    {
        $query->bind_param('sissssssiiiiiiiiii', $_serverAddress, $_serverPort, $_info, $_pastebin, $_players, $_playerList, $_gameVersion, $_time, $_privateBeta, $_staffRA, $_friendlyFire, $_geoblocking, $_modded, $_whitelist, $_accessRestrictions, $_emailSet, $_enforceSameIp, $_enforceSameAsn);

        if($query->execute()) {
            echo "Big dump arrived. Serverlist updated.";
        } else {
            echo $query->error;
        }
    } else {
        echo "____________________\n";
        print_r($mysqli->error_list);
        echo "____________________\n";
    }

    var_dump(array($_serverAddress, $_serverPort, $_info, $_pastebin, $_players, $_playerList, $_gameVersion, $_time, $_privateBeta, $_staffRA, $_friendlyFire, $_geoblocking, $_modded, $_whitelist, $_accessRestrictions, $_emailSet, $_enforceSameIp, $_enforceSameAsn));
} else {
    $_time = date('Y-m-d H:i:s');

    $query = $mysqli->prepare("UPDATE servers SET players = ?, last_request = ?, enforce_same_ip = ?, enforce_same_asn = ? WHERE address = ? AND connection_port = ?");
    $query->bind_param('ssiisi', $_players, $_time, $_enforceSameIp, $_enforceSameAsn, $_serverAddress, $_serverPort);

    if($query->execute()) {
        echo "Small dump arrived. Serverlist updated.";
    } else {
        echo $query->error;
    }
}

?>