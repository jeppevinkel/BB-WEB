<?php
$NoSessions = true;
$NoHeader = true;
$ApiPage = true;
$SkipCsrfValidation = true;
$DisableSessionValidation = true;
$officialagreement = false; //set true when agreement is live
require_once('../../config.php');
/*error_reporting(E_ALL);
ini_set('display_errors', '1');/**/
if (!$ServerPrimary) die("Function disabled on backup server.");

require_once('../../vendor/autoload.php');
use GeoIp2\Database\Reader;

$client = new Predis\Client([
    'scheme' => 'tcp',
    'host' => $RedisHost,
    'password' => $RedisPassword,
    'port' => $RedisPort - 1,
]);

$b = (isset($_POST['update']) && $_POST['update']);
$startup = isset($_POST["startup"]) && $_POST["startup"] == 1;

$verified = false;
$newToken = "";
$messages = array();
$actions = array();
$error = "";
$tokenSet = isset($_POST['passcode']) && strlen($_POST['passcode']) > 0;

if (isset($_POST['ip']) && isset($_POST['port']))
{
    if (!preg_match('/^[0-9a-zA-Z\.\-:]{1,39}$/', $_POST['ip'])) EndWithError('Invalid or too long \'ip\' parameter.');
    if (!preg_match('/^[0-9]+$/', $_POST['port'])) EndWithError("Invalid 'port' parameter.");
    if (isset($_POST['passcode'])) {
        $pass = $_POST['passcode'];
        if (strlen($pass) > 16) $pass = substr($pass, 0, 16);
    }
    $ip = $_POST['ip'];
    $port = $_POST['port'];
    IPCheck($ip);
    $serverAddress = gethostbyname($ip);
    $geoUpdate = false;
    $distanceIp = $serverAddress;
    $cache = $client->get("account-" . $ip);
    if ($cache == null) $deserialize = array();
    else $deserialize = json_decode($cache, true);

    $VerKeyupdated = false;

    //Token generation for new servers
    if (!$tokenSet && $cache == null) EndNotVerified();
    else if ($deserialize["hoster_id"] != null) {
        if ($_POST['players'] != "0" && !$startup && !$b) {
            $pass = "VERIFIED HOST000";
            $newToken = $pass;
            $verified = true;

            $geoUpdate = true;
            $VerKeyupdated = true;
        } else {
            $verified = false;
            $messages[] = 'HOSTER: Please wait 60 seconds.';
            EndScript($verified, $newToken, $messages, $actions, $error);
        }
    }
    else {
        if ($deserialize["token"] == null) {
            $pass = GeneratePassword(16);
            $newToken = $pass;
            $verified = true;
            $stmt = $pdo->prepare("UPDATE `tokens` SET `token` = :passcode WHERE `account_id` = :acid");
            $stmt->bindValue(':acid', $deserialize["account_id"], PDO::PARAM_INT);
            $stmt->bindValue(':passcode', $pass, PDO::PARAM_STR);
            $stmt->execute();
            $stmt = null;

            $deserialize["token"] = $pass;
            $client->setex("account-" . $ip, $AccountCacheTTL, json_encode($deserialize));

            $geoUpdate = true;
            $VerKeyupdated = true;
        }
        else if (!$tokenSet) EndNotVerified();
    }

    // This is a check to see if HOST IPS have a wrong passcode, if they have a wrong passcode it will generate the correct one.
    // If this becomes an performance issue, delete it. - Rin
    if (($VerKeyupdated == false || $VerKeyupdated == null) && (substr($_POST['passcode'],0,16) != "VERIFIED HOST000" && $_POST['passcode'] != "VERIFIED HOST - ACCEPTED")) {
        $IpData = $client->get("account-" . $_SERVER['REMOTE_ADDR']);
        if ($IpData != null) {
            $IpDataDecoded = json_decode($IpData, true);
            if ($IpDataDecoded['hoster_id'] != null) {
                if ($_POST['players'] != "0") {
                    $pass = "VERIFIED HOST000";
                    $newToken = $pass;
                    $verified = true;

                    $geoUpdate = true;
                } else {
                    $verified = false;
                    $messages[] = 'HOSTER: Please wait 60 seconds.';
                    EndScript($verified, $newToken, $messages, $actions, $error);
                }
            }
        }
    }

    //Checking if server is authorized to use hosting provider key
    if (isset ($_POST['passcode']) && ($_POST['passcode'] == "VERIFIED HOST - ACCEPTED" || substr($_POST['passcode'],0,16) == "VERIFIED HOST000")) {
        $realIpData = $client->get("account-" . $_SERVER['REMOTE_ADDR']);
        if ($realIpData == null) EndWithError('Your IP (' . $_SERVER['REMOTE_ADDR'] . ') does not belongs to verified hosting provider.');
        $realIpDeserialize = json_decode($realIpData, true);
        if ($realIpDeserialize['hoster_id'] == null) EndWithError('Your IP (' . $_SERVER['REMOTE_ADDR'] . ') does not belongs to verified hosting provider.');
        if ($cache == null) EndWithError('IP provided by you (' . $_POST['ip'] . ') does not belongs to verified hosting provider.');
        if ($deserialize['hoster_id'] == null) EndWithError('IP provided by you (' . $_POST['ip'] . ') does not belongs to verified hosting provider.');
        $accepted = 1;
        if ($deserialize['geo_update'] == true) $geoUpdate = true;
        else if ($ip != $serverAddress && $serverAddress != $deserialize['resolved_ip']) $geoUpdate = true;
        $account_id = $deserialize["account_id"];
        //if ($row['distance_ip'] != null) $distanceIp = gethostbyname($row['distance_ip']);
    }
    else {
        $stmt = $pdo->prepare("SELECT `account_id`, `ip`, `dynamic`, `accepted`, `officialaccepted`, `longitude`, `latitude`, `resolved_ip`, `distance_ip` FROM `tokens` WHERE BINARY `token` = :passcode AND `hoster_id` is null LIMIT 1");
        $stmt->bindValue(':passcode', $pass, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt = null;
        if ($row == null) {
            if ($cache == null) {
                $verified = false;
                $messages[] = 'Verification passcode is not correct.';
                EndScript($verified, $newToken, $messages, $actions, $error);
            }
            else {
                if ($deserialize["token"] != null) EndNotVerified();
                if ($deserialize["hoster_id"] != null) EndNotVerified();
                $pass = GeneratePassword(16);
                $newToken = $pass;
                $verified = true;
                $stmt = $pdo->prepare("UPDATE `tokens` SET `token` = :passcode WHERE `account_id` = :acid");
                $stmt->bindValue(':acid', $deserialize['account_id'], PDO::PARAM_INT);
                $stmt->bindValue(':passcode', $pass, PDO::PARAM_STR);
                $stmt->execute();
                $stmt = null;
                $geoUpdate = true;

                $deserialize["token"] = $pass;
                $client->setex("account-" . $ip, $AccountCacheTTL, json_encode($deserialize));
            }
        }
        else {
            if ($row['longitude'] == null || $row['latitude'] == null) $geoUpdate = true;
            else if ($ip != $serverAddress && $serverAddress != $row['resolved_ip']) $geoUpdate = true;
        }
        if ($row['distance_ip'] != null) $distanceIp = gethostbyname($row['distance_ip']);

        //if ($row['accepted'] == 0) die("Please accept our rules of verified servers. Please visit: ");
        if ($row['ip'] != $ip) {
            if ($row['dynamic'] == 1) {
                if (!$b) {
                    $verified = true;
                    $messages[] = 'IP mismatch, but your IP is flagged as dynamic. Make sure that all servers on other IPs are disabled and wait few minutes or restart the server.';
                    EndScript($verified, $newToken, $messages, $actions, $error);
                }
                $stmt = $pdo->prepare("UPDATE `tokens` SET `ip` = :ip WHERE `account_id` = :acid");
                $stmt->bindValue(':acid', $row['account_id'], PDO::PARAM_INT);
                $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
                $stmt->execute();
                $stmt = null;

                $stmt = null;
                $geoUpdate = true;
            } else {
                $stmt = $pdo->prepare("SELECT `token` FROM `tokens` WHERE `ip` = :ip AND `hoster_id` is null LIMIT 1");
                $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
                $stmt->execute();
                $row3 = $stmt->fetch();
                $stmt = null;
                if ($row3['token'] != NULL && $newToken == "") EndWithError('IP Mismatch. This token does not belong to you.');

                $stmt = $pdo->prepare("SELECT `account_id`, `ip`, `token` FROM `tokens` WHERE `token` = :passcode AND `hoster_id` is null LIMIT 1");
                $stmt->bindValue(':passcode', $_POST['passcode'], PDO::PARAM_STR);
                $stmt->execute();
                $row2 = $stmt->fetch();
                $stmt = null;

                if ($row2['token'] == $_POST['passcode']) {
                    $pass2 = GeneratePassword(16);
                    $newToken = $pass2;
                    $verified = true;
                    $stmt = $pdo->prepare("UPDATE `tokens` SET `token` = :passcode WHERE `ip` = :ip");
                    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
                    $stmt->bindValue(':passcode', $pass2, PDO::PARAM_STR);
                    $stmt->execute();
                    $stmt = null;
                    $geoUpdate = true;

                    $deserialize["token"] = $pass2;
                    $client->setex("account-" . $ip, $AccountCacheTTL, json_encode($deserialize));
                }
                else {
                    $verified = false;
                    $messages[] = 'IP or Passcode Invalid. Contact the verification team (server.verification@scpslgame.com).';
                    EndScript($verified, $newToken, $messages, $actions, $error);
                }
            }
        }
        $accepted = $row['accepted'];
        $account_id = $row['account_id'];
        $officialserver = false;
        $officialaccepted = null;
        $stmt = $pdo->prepare("SELECT `official` FROM `servers` WHERE `account_id` = :accountid");
        $stmt->bindValue(':accountid', $row['account_id'], PDO::PARAM_INT);
        $stmt->execute();
        while ($row2 = $stmt -> fetch()){
            if ($row2['official'] != 0) {
                $officialserver = true;
                break;
            }
        }
        if ($officialserver){
            $officialaccepted = $row['officialaccepted'];
        }
        $stmt = null;
    }

    if (isset($_POST['players']))
    {
        //if (!isset($_POST['version']) || $_POST['version'] < 2) die('Please update your server.');
        $stmt = $pdo -> prepare("SELECT `id`, `action`, `last_online` FROM `servers` WHERE `account_id` = :acid AND `port` = :nport LIMIT 1");
        $stmt -> bindValue(':acid', $account_id, PDO::PARAM_INT);
        $stmt -> bindValue(':nport', $port, PDO::PARAM_STR);
        $stmt -> execute();
        $row = $stmt -> fetch();
        $stmt = null;
        //$append =  ":Message - DEBUG (ignore this) Connection IP - " . $_SERVER['REMOTE_ADDR'] . ":::";

        if (!preg_match('/^[0-9]{1,3}\/[0-9]{1,3}$/', $_POST['players'])) $_POST['players'] = "0/0";
        if (($b || $row == null) && substr_count($_POST['info'], ":[:BREAK:]:") < 2) {
            if ($newToken != "") {
                EndWithError('Malformed server info: ' . $_POST['info']);
                if ($b) {
                    $infoSplit = explode(":[:BREAK:]:", $_POST['info']);
                    if (!preg_match('/^[a-zA-Z0-9]{8}$/', $infoSplit[1])) EndWithError('Invalid pastebin ID - ' . $infoSplit[1]);
                }
            }
        }

        if ($geoUpdate && ($cache == null || $deserialize["distance_override"] == 0)) {
            $cityReader = new Reader('../../scripts/lib/databases/GeoLite2-City.mmdb');
            $serverRecord = $cityReader->city($distanceIp);

            $stmt = $pdo -> prepare("UPDATE `tokens` SET longitude = :lon, `latitude` = :lat, `continent` = :cont, `country` = :country, `resolved_ip` = :resolved WHERE account_id = :acid");
            $stmt -> bindValue(':acid', $account_id, PDO::PARAM_INT);
            $stmt -> bindValue(':lon', $serverRecord->location->longitude);
            $stmt -> bindValue(':lat', $serverRecord->location->latitude);
            $stmt -> bindValue(':cont', $serverRecord->continent->code, PDO::PARAM_STR);
            $stmt -> bindValue(':country', $serverRecord->country->isoCode, PDO::PARAM_STR);
            if ($serverAddress == $ip) $stmt -> bindValue(':resolved', null);
            else $stmt -> bindValue(':resolved', $serverAddress, PDO::PARAM_STR);
            $stmt -> execute();
            $stmt = null;

            $deserialize["geo_update"] = false;
            $client->setex("account-" . $ip, $AccountCacheTTL, json_encode($deserialize));
        }

        if ($row == null) {
            $stmt = $pdo -> prepare("INSERT INTO `servers` (`account_id`, created, `last_online`, `port`) VALUES (:acid, :created, :lastonline, :nport)");
            $stmt -> bindValue(':acid', $account_id, PDO::PARAM_INT);
            $stmt -> bindValue(':nport', $port, PDO::PARAM_STR);
            $stmt -> bindValue(':created', gmdate("Y-m-d H:i:s"), PDO::PARAM_STR);
            $stmt -> bindValue(':lastonline', gmdate("Y-m-d H:i:s"), PDO::PARAM_STR);
            $stmt -> execute();
            $stmt = null;
        }
        else if ($b) {
            $infoPrivBeta = isset($_POST["privateBeta"]) && $_POST["privateBeta"] == "True" ? true : false;
            $infoStaffRA = isset($_POST["staffRA"]) && $_POST["staffRA"] == "True" ? true : false;
            $infoFF = isset($_POST["friendlyFire"]) && $_POST["friendlyFire"] == "True" ? true : false;
            $infoModded = isset($_POST["modded"]) && $_POST["modded"] == "True" ? true : false;
            $infoWL = isset($_POST["whitelist"]) && $_POST["whitelist"] == "True" ? true : false;

            $data = ["ip" => $_POST['ip'], "port" => $port, "players" => $_POST['players']];
            $client->setex("server-data-" . $row["id"], $ServerInactivityTime, json_encode($data));
            $client->setex("server-info-" . $row["id"], $ServerInactivityInfoTime, json_encode(["v" => 2, "info" => $_POST['info'], "gameVersion" => $_POST['gameVersion'], "pastebin" => $_POST['pastebin'], "playersList" => json_decode($_POST['playersList'], true), "PrivBeta" => $infoPrivBeta, "RA" => $infoStaffRA, "FF" => $infoFF, "Modded" => $infoModded, "WL" => $infoWL]));
        }
        else {
            $data = ["ip" => $_POST['ip'], "port" => $port, "players" => $_POST['players']];
            $client->setex("server-data-" . $row["id"], $ServerInactivityTime, json_encode($data));
        }

        $verified = true;
        if ($row['action'] != null) {
            if ($row['action'] == 1) $actions[] = 'Restart';
            else if ($row['action'] == 2) $actions[] = 'RoundRestart';
            else if ($row['action'] == 3) $actions[] = 'UpdateData';
            else if ($row['action'] == 4) $actions[] = 'RefreshKey';

            $stmt = $pdo -> prepare("UPDATE `servers` SET `action` = null WHERE `id` = :sid");
            $stmt -> bindValue(":sid", $row["id"], PDO::PARAM_INT);
            $stmt -> execute();
            $stmt = null;
        }

        $today = gmdate("Y-m-d");

        if ($row['last_online'] == null || $row['last_online'] != $today) {
            $stmt = $pdo -> prepare("UPDATE `servers` SET `last_online` = :today WHERE `id` = :sid");
            $stmt -> bindValue(":today", $today, PDO::PARAM_STR);
            $stmt -> bindValue(":sid", $row["id"], PDO::PARAM_INT);
            $stmt -> execute();
            $stmt = null;
        }

        if ($accepted == 0) $messages[] = "SERVER NOT VISIBLE ON THE LIST!!! You need to sign verified server owner agreement. Please visit: https://staff.scpslgame.com/forms/verifiedserveragreement.php?token=" . hash("sha1", $pass);
        else if ($accepted == 2) $messages[] = "We have updated our verified server owner agreement. You need to sign new version to keep the server on the list. Please visit: https://staff.scpslgame.com/forms/verifiedserveragreement.php?token=" . hash("sha1", $pass);
        if ($officialagreement && $officialserver == true && $officialaccepted == 0) $messages[] = "SERVER NOT VISIBLE ON THE LIST!!! You need to sign the Official Server Host Agreement. Please visit: https://staff.scpslgame.com/forms/officialserveragreement.php?token=" . hash("sha1", $pass);
        else if ($officialagreement && $officialserver == true && $officialaccepted == 2) $messages[] = "We have updated the Official Server Host Agreement. Please visit: https://staff.scpslgame.com/forms/officialserveragreement.php?token=" . hash("sha1", $pass);
    }
    else
        $error = "Variable 'players' not set!";
}
else
    $error = "Variable 'ip' or 'port' not set!";

EndScript($verified, $newToken, $messages, $actions, $error);
function EndScript($verified, $newToken, $messages, $actions, $error) {
    $output = array();
    if (strlen($error) > 0) {
        $output["success"] = false;
        $output["error"] = $error;
    }
    else {
        $output["success"] = true;
        $output["verified"] = $verified;
        if (strlen($newToken) > 0) $output["token"] = $newToken;
        if (sizeof($messages) > 0) $output["messages"] = $messages;
        if (sizeof($actions) > 0) $output["actions"] = $actions;
    }
    die (json_encode($output));
}

function EndWithError($error) {
    die (json_encode(["success" => false, "error" => $error]));
}

function EndNotVerified() {
    EndScript(false, "", [], [], "");
}