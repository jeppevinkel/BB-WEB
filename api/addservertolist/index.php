<?php
date_default_timezone_set('CET');
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../secrets/mysql-secrets.php';

$latestVersion = "2.0.1";

$response = array();

$_errs = array();

if (isset($_POST['ip'])) {
    if (!preg_match('/^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?|^((http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $_POST['ip'])){
        $response['error'] = 'Invalid or too long ip.';
        print_r(json_encode($response));
        exit();
    }
    $_serverAddress = $_POST['ip'];
} else {
    array_push($_errs, "No ip");
}
if (isset($_POST['port'])) {
    if (!preg_match('/^[0-9]+$/', $_POST['port'])) {
        $response['error'] = 'Invalid port.';
        print_r(json_encode($response));
        exit();
    }
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

if (isset($_POST['info'])) {
    $_info = $_POST['info'];
}
if (isset($_POST['playerList'])) {
    $_playerList = $_POST['playerList'];
}
if (isset($_POST['playerList'])) {
    $_playerList = $_POST['playerList'];
}
if (isset($_POST['pastebin'])) {
    $_pastebin = $_POST['pastebin'];
}
if (isset($_POST['gameVersion'])) {
    $_gameVersion = $_POST['gameVersion'];
}
if (isset($_POST['privateBeta'])) {
    $_privateBeta = ($_POST['privateBeta'] == "True" ? 1 : 0);
}
if (isset($_POST['staffRA'])) {
    $_staffRA = ($_POST['staffRA'] == "True" ? 1 : 0);
}
if (isset($_POST['friendlyFire'])) {
    $_friendlyFire = ($_POST['friendlyFire'] == "True" ? 1 : 0);
}
if (isset($_POST['geoblocking'])) {
    $_geoblocking = intval($_POST['geoblocking']);
}
if (isset($_POST['modded'])) {
    $_modded = ($_POST['modded'] == "True" ? 1 : 0);
}
if (isset($_POST['whitelist'])) {
    $_whitelist = ($_POST['whitelist'] == "True" ? 1 : 0);
}
if (isset($_POST['accessRestrictions'])) {
    $_accessRestrictions = ($_POST['accessRestrictions'] == "True" ? 1 : 0);
}
if (isset($_POST['emailSet'])) {
    $_emailSet = ($_POST['emailSet'] == "True" ? 1 : 0);
}

$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_dbname);
//$mysqli->report_mode = MYSQLI_REPORT_ALL;
//mysqli_report(MYSQLI_REPORT_ALL);
if (isset($_POST['maxPlayers'])) {
    $_time = date('Y-m-d H:i:s');

    if (isset($_POST['apiToken'])) {
        $bigDumpSql = "
        INSERT INTO servers (address, connection_port, info, pastebin, players, playerlist, server_version, last_request, private_beta, staff_ra, friendly_fire, geoblocking, modded, whitelist, access_restrictions, email_set, enforce_same_ip, enforce_same_asn, curPlayers, maxPlayers, apiToken)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            enforce_same_asn = VALUES(enforce_same_asn),
            curPlayers = VALUES(curPlayers),
            maxPlayers = VALUES(maxPlayers),
            apiToken = VALUES(apiToken)";
    } else{
        $bigDumpSql = "
        INSERT INTO servers (address, connection_port, info, pastebin, players, playerlist, server_version, last_request, private_beta, staff_ra, friendly_fire, geoblocking, modded, whitelist, access_restrictions, email_set, enforce_same_ip, enforce_same_asn, curPlayers, maxPlayers)
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
            enforce_same_asn = VALUES(enforce_same_asn),
            curPlayers = VALUES(curPlayers),
            maxPlayers = VALUES(maxPlayers),";
    }

    if($query = $mysqli->prepare($bigDumpSql))
    {
        if (isset($_POST['apiToken'])) {
            $query->bind_param('sissssssiiiiiiiiiiiis', $_serverAddress, $_serverPort, $_info, $_pastebin, $_players, $_playerList, $_gameVersion, $_time, $_privateBeta, $_staffRA, $_friendlyFire, $_geoblocking, $_modded, $_whitelist, $_accessRestrictions, $_emailSet, $_enforceSameIp, $_enforceSameAsn, $_POST['curPlayers'], $_POST['maxPlayers'], $_POST['apiToken']);
        } else{
            $query->bind_param('sissssssiiiiiiiiiiii', $_serverAddress, $_serverPort, $_info, $_pastebin, $_players, $_playerList, $_gameVersion, $_time, $_privateBeta, $_staffRA, $_friendlyFire, $_geoblocking, $_modded, $_whitelist, $_accessRestrictions, $_emailSet, $_enforceSameIp, $_enforceSameAsn, $_POST['curPlayers'], $_POST['maxPlayers']);
        }

        if($query->execute()) {
            $response['success'] = true;
            $response['type'] = 2;
        } else {
            $response['error'] = $query->error;
        }
    } else {
        $response['error'] = $mysqli->error_list;
    }
} elseif (isset($_emailSet)) {
    $_time = date('Y-m-d H:i:s');

    if (isset($_POST['apiToken'])) {
        $bigDumpSql = "
        INSERT INTO servers (address, connection_port, info, pastebin, players, playerlist, server_version, last_request, private_beta, staff_ra, friendly_fire, geoblocking, modded, whitelist, access_restrictions, email_set, enforce_same_ip, enforce_same_asn, apiToken)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            enforce_same_asn = VALUES(enforce_same_asn),
            apiToken = VALUES(apiToken)";
    } else{
        $bigDumpSql = "
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
            enforce_same_asn = VALUES(enforce_same_asn)";
    }

    if($query = $mysqli->prepare($bigDumpSql))
    {
        if (isset($_POST['apiToken'])) {
            $query->bind_param('sissssssiiiiiiiiiis', $_serverAddress, $_serverPort, $_info, $_pastebin, $_players, $_playerList, $_gameVersion, $_time, $_privateBeta, $_staffRA, $_friendlyFire, $_geoblocking, $_modded, $_whitelist, $_accessRestrictions, $_emailSet, $_enforceSameIp, $_enforceSameAsn, $_POST['apiToken']);
        } else{
            $query->bind_param('sissssssiiiiiiiiii', $_serverAddress, $_serverPort, $_info, $_pastebin, $_players, $_playerList, $_gameVersion, $_time, $_privateBeta, $_staffRA, $_friendlyFire, $_geoblocking, $_modded, $_whitelist, $_accessRestrictions, $_emailSet, $_enforceSameIp, $_enforceSameAsn);
        }

        if($query->execute()) {
            $response['success'] = true;
            $response['type'] = 2;
        } else {
            $response['error'] = $query->error;
        }
    } else {
        $response['error'] = $mysqli->error_list;
    }
} else {
    $_time = date('Y-m-d H:i:s');

    if (isset($_POST['curPlayers'])) {
        $query = $mysqli->prepare("UPDATE servers SET players = ?, last_request = ?, enforce_same_ip = ?, enforce_same_asn = ?, curPlayers = ? WHERE address = ? AND connection_port = ?");
        $query->bind_param('ssiiisi', $_players, $_time, $_enforceSameIp, $_enforceSameAsn, $_POST['curPlayers'], $_serverAddress, $_serverPort);
    }else{
        $query = $mysqli->prepare("UPDATE servers SET players = ?, last_request = ?, enforce_same_ip = ?, enforce_same_asn = ? WHERE address = ? AND connection_port = ?");
        $query->bind_param('ssiisi', $_players, $_time, $_enforceSameIp, $_enforceSameAsn, $_serverAddress, $_serverPort);
    }

    if($query->execute()) {
        $response['success'] = true;
        $response['type'] = 1;
    } else {
        $response['error'] = $query->error;
    }
}

if (isset($_POST['pluginVersion'])) {
    if ($_POST['pluginVersion'] != $latestVersion) {
        $response['update'] = true;
        $response['latestVersion'] = $latestVersion;
    }
}

print_r(json_encode($response));

?>