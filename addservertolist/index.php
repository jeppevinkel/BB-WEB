<?php
date_default_timezone_set('CET');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../secrets/mysql-secrets.php';

$_errs = array();

if (isset($_POST['ip'])) {
    $_serverAddress = $_POST['ip'];
} else {
    array_push($_errs, "No ip");
}
if (isset($_POST['port'])) {
    $_serverPort = intval($_POST['port']);
} else {
    array_push($_errs, "No port");
}
if (isset($_POST['players'])) {
    $_players = $_POST['players'];
} else {
    array_push($_errs, "No players");
}
if (isset($_POST['enforceSameIp'])) {
    $_enforceSameIp = ($_POST['enforceSameIp'] == "True" ? 1 : 0);
} else {
    array_push($_errs, "No enforceSameIp");
}
if (isset($_POST['enforceSameAsn'])) {
    $_enforceSameAsn = ($_POST['enforceSameAsn'] == "True" ? 1 : 0);
} else {
    array_push($_errs, "No enforceSameAsn");
}
if (count($_errs) >= 1) {
    print_r(json_encode($_errs));
    exit();
}

$_valCount = 0;

if (isset($_POST['info'])) {
    $_info = $_POST['info'];
    $_valCount++;
}
if (isset($_POST['playerList'])) {
    $_playerList = $_POST['playerList'];
    $_valCount++;
}
if (isset($_POST['playerList'])) {
    $_playerList = $_POST['playerList'];
    $_valCount++;
}
if (isset($_POST['pastebin'])) {
    $_pastebin = $_POST['pastebin'];
    $_valCount++;
}
if (isset($_POST['gameVersion'])) {
    $_gameVersion = $_POST['gameVersion'];
    $_valCount++;
}
if (isset($_POST['privateBeta'])) {
    $_privateBeta = ($_POST['privateBeta'] == "True" ? 1 : 0);
    $_valCount++;
}
if (isset($_POST['staffRA'])) {
    $_staffRA = ($_POST['staffRA'] == "True" ? 1 : 0);
    $_valCount++;
}
if (isset($_POST['friendlyFire'])) {
    $_friendlyFire = ($_POST['friendlyFire'] == "True" ? 1 : 0);
    $_valCount++;
}
if (isset($_POST['geoblocking'])) {
    $_geoblocking = intval($_POST['geoblocking']);
    $_valCount++;
}
if (isset($_POST['modded'])) {
    $_modded = ($_POST['modded'] == "True" ? 1 : 0);
    $_valCount++;
}
if (isset($_POST['whitelist'])) {
    $_whitelist = ($_POST['whitelist'] == "True" ? 1 : 0);
    $_valCount++;
}
if (isset($_POST['accessRestrictions'])) {
    $_accessRestrictions = ($_POST['accessRestrictions'] == "True" ? 1 : 0);
    $_valCount++;
}
if (isset($_POST['emailSet'])) {
    $_emailSet = ($_POST['emailSet'] == "True" ? 1 : 0);
    $_valCount++;
}

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);
//$mysqli->report_mode = MYSQLI_REPORT_ALL;
//mysqli_report(MYSQLI_REPORT_ALL);

if ($_valCount == 13) {
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
        print_r($mysqli->error_list);
    }
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