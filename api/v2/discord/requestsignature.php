<?php
$NoSessions = true;
$NoHeader = true;
$ApiPage = true;
$SkipCsrfValidation = true;
$DisableSessionValidation = true;
$LoadGlobal = true;
require_once '../../../config.php';
require_once '../../../vendor/autoload.php';
Predis\Autoloader::register();
use GeoIp2\Database\Reader;
use DiscordWebhooks\Client;
use DiscordWebhooks\Embed;
use Predis\Autoloader;

$server = $ServerName;

/*error_reporting(E_ALL);
ini_set('display_errors', '1');/**/


if (!isset($_POST['token'])) die('Missing token');
if (!isset($_POST['publickey'])) die('Missing public key hash');
$pattern='/^[A-Z0-9]+$/';

$banned = 0;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://discordapp.com/api/v6/users/@me");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $_POST['token']
));
curl_setopt($ch, CURLOPT_FAILONERROR, true);
$server_output = curl_exec($ch);

if (curl_error($ch)) {
    curl_close($ch);
    http_response_code(401);
    die("Failed to auth with Discord.");
}

$userdata = json_decode($server_output, true);

$userid = $userdata["id"] . '@discord';
$username = $userdata["username"] . '#' . $userdata["discriminator"];


$stmt = $pdoGlobal->prepare("SELECT `UserID` FROM `GlobalBans` WHERE `UserID` = :userid");
$stmt->bindValue(':userid', $userid, PDO::PARAM_STR);
$stmt->execute();
$resultBan = $stmt->fetch();

$now = date('Y-m-d H:i:s');
$banned = 0;

while ($row = $stmt->fetch()) {
    if ($row['Expires'] == null || strtotime($row['Expires']) > strtotime($now)) {
        if ($row['Reason'] == 1) $banned = 1;
        else if ($row['Reason'] == 2 && $banned != 1) $banned = 2;
        else if ($row['Reason'] == 4 && $banned != 1 && $banned != 2) $muted = 4;
        else if ($row['Reason'] == 3 && $banned == 0) $muted = 3;
    }
}

$test = false;
$expiration = "+15 minutes";

$stmt = $pdoGlobal -> prepare("SELECT Badge, Description2, Badges.Text, Badges.Color, Badges.BadgeType, Badges.GlobalBanning, Badges.Management FROM staff_global.PlayerBadges INNER JOIN Badges AS Badges ON PlayerBadges.Badge = Badges.ID WHERE PlayerBadges.UserID = :userid");
$stmt -> bindValue(':userid', $userid, PDO::PARAM_STR);
$stmt -> execute();
$result = $stmt -> fetch();
$noBan = false;
$bypassGeo = false;
$bypassWL = false;
$bypass = false;
$overwatchEnabled = false;
$remoteadminEnabled = false;
$remoteadminEverywhere = false;
$globalbanningEnabled = false;
$ValidWithoutBadge = false;

$abuse = false;
$noperms = false;

$manager = false;
$globalMod = false;

if ($result != null) {
    if ($result['BadgeType'] != 0) $isStaff = true;
    $manager = $result['Management'] == 1;
    $globalMod = $result['GlobalBanning'] == 1;
}

//Check Security Clearances

$client = new \Predis\Client([
    'scheme' => 'tcp',
    'host' => $RedisHost,
    'password' => $RedisPassword,
    'port' => $RedisPort,
]);

$cacheKey = "clearance-discord-" . $userdata["id"];

$cache = $client->get($cacheKey);

if ($cache != null) {
    $checkPerms = json_decode($cache, true);
    if ($checkPerms['ValidWithoutBadge'] == 1) $ValidWithoutBadge = true;
    if (($ValidWithoutBadge || $isStaff) && !$noperms) {
        if (!$abuse && !$noperms) {
            if ($checkPerms['BypassVAC'] == 1) $bypass = true;
            if ($checkPerms['BypassWL'] == 1) $bypassWL = true;
            if ($checkPerms['BypassGeoBlocks'] == 1) $bypassGeo = true;
            if ($checkPerms['BypassBans'] == 1) $noBan = true;
            if ($checkPerms['RemoteAdmin'] == 1) $remoteadminEnabled = true;
            else if ($checkPerms['RemoteAdmin'] == 2) {
                $remoteadminEnabled = true;
                $remoteadminEverywhere = true;
            }
        }

        if (!$noperms) {
            if ($checkPerms['Overwatch'] == 1) $overwatchEnabled = true;
        }
    }
}

try {
    $reader = new Reader('../scripts/lib/databases/GeoLite2-ASN.mmdb');
    $record = $reader->asn($_SERVER['REMOTE_ADDR']);
    $asn = $record->autonomousSystemNumber;
} catch (Exception $e){
    //IP Address not found in GeoIP database. Asking free API.

    $url = "https://freeapi.dnslytics.net/v1/ip2asn/". $_SERVER['REMOTE_ADDR'] ."";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    $apiresult = curl_exec($ch);
    curl_close($ch);

    $apiresult = json_decode($apiresult, true);
    $asn = $apiresult['asn'];

}

if (!$noBan && !$bypass && !($isStaff && !$abuse)) {
    $stmt = $pdoGlobal->prepare("SELECT `ASN` FROM `ASNBans` WHERE `ASN` = :asn"); // check if their ASN is blacklisted in the database
    $stmt->bindValue(':asn', $asn, PDO::PARAM_INT);
    $stmt->execute();
    $rowcount = $stmt->rowCount();
    $ASNresult = $stmt -> fetch();
    $stmt = null; // close database connection
    if ($ASNresult != null) { // ASN is blacklisted.
        $banned = "The ASN of the Internet Service Provider you are connecting from is globally blocked - ASN" . $ASNresult['ASN']; //display ban message
    }

    $stmt = $pdoGlobal->prepare("SELECT `IP` FROM `ASNBans` WHERE `IP` = :ip"); //fetch all IP ranges.
    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $stmt->execute();
    $rowBanned = $stmt->fetch();
    if ($rowBanned != null) { // IP is blacklisted.
        $banned = "The IP you are connecting with is globally blocked";
    }
}

$stmt = $pdoGlobal -> prepare("SELECT `ID`, `CustomUserID` FROM staff_global.CustomUserIDs WHERE `UserID` = :userid");
$stmt -> bindValue(':userid', $userid, PDO::PARAM_STR);
$stmt -> execute();
$cuidResult = $stmt -> fetch();

$cbid = null;

if ($cuidResult != null) {
    $cbid = $cuidResult["ID"];
    $userid = $cuidResult["CustomUserID"];
}

$stmt = null; // close database connection

$plaintext = "User ID: " . $userid;
if ($cbid != null) $plaintext .= "<br>CBID: " . $cbid;
$plaintext .= "<br>Nickname: " . base64_encode($username);
$plaintext .= "<br>Request IP: " . $_SERVER['REMOTE_ADDR'];
$plaintext .= "<br>Global ban: " . $banned;
$plaintext .= "<br>Issuence time: " . gmdate("Y-m-d H:i:s");
$plaintext .= "<br>Expiration time: " . gmdate('Y-m-d H:i:s', strtotime($expiration));
$plaintext .= "<br>Issued by: " . $server;
$plaintext .= "<br>Usage: Authentication";
if (isset($_POST['DNT'])) $plaintext .= "<br>Do Not Track: YES";
$plaintext .= "<br>Bypass bans: " . ($noBan ? "YES" : "NO");
$plaintext .= "<br>Bypass geo restrictions: " . ($bypassGeo ? "YES" : "NO");
$plaintext .= "<br>Bypass WL: " . ($bypassWL ? "YES" : "NO");
$plaintext .= "<br>Public key: " . base64_encode($_POST['publickey']);
$plaintext .= "<br>Test signature: " . ($test ? "YES" : "NO");
$plaintext .= "<br>Auth Version: 2";

echo $plaintext;

$signature ="";
openssl_sign($plaintext, $signature, $privatekey, OPENSSL_ALGO_SHA256);

echo '<br>Signature: ' . base64_encode($signature);
echo '<br>=== SECTION ===<br>';

if ($result != null) {
    $expiration = "+75 minutes";
    $plaintext = "User ID: " . $userid;
    $plaintext .= "<br>Nickname: " . base64_encode($username);
    $plaintext .= "<br>Issuence time: " . gmdate("Y-m-d H:i:s");
    $plaintext .= "<br>Expiration time: " . gmdate('Y-m-d H:i:s', strtotime($expiration));
    $plaintext .= "<br>Issued by: " . $server;
    $plaintext .= "<br>Usage: Badge request";
    $plaintext .= "<br>Badge text: " . $result['Text'];
    $plaintext .= "<br>Badge color: " . $result['Color'];
    $plaintext .= "<br>Badge type: " . $result['BadgeType'];
    $plaintext .= "<br>Staff: " . ($isStaff ? "YES" : "NO");
    $plaintext .= "<br>Remote admin: " . ($remoteadminEnabled && $isStaff ? "YES" : "NO");
    $plaintext .= "<br>Management: " . ($remoteadminEverywhere && $manager ? "YES" : "NO");
    $plaintext .= "<br>Overwatch mode: " . ($overwatchEnabled ? "YES" : "NO");
    $plaintext .= "<br>Global banning: " . ($remoteadminEverywhere && $globalMod ? "YES" : "NO");
    $plaintext .= "<br>Public key: " . base64_encode($_POST['publickey']);
    $plaintext .= "<br>Test signature: " . ($test ? "YES" : "NO");
    $plaintext .= "<br>Token Version: 2";
    echo $plaintext;

    openssl_sign($plaintext, $signature, $privatekey, OPENSSL_ALGO_SHA256);

    echo '<br>Signature: ' . base64_encode($signature);
} else if ($ValidWithoutBadge && ($remoteadminEnabled || $overwatchEnabled)) {
    $expiration = "+75 minutes";
    $plaintext = "User ID: " . $userid;
    $plaintext .= "<br>Nickname: " . base64_encode($username);
    $plaintext .= "<br>Issuence time: " . gmdate("Y-m-d H:i:s");
    $plaintext .= "<br>Expiration time: " . gmdate('Y-m-d H:i:s', strtotime($expiration));
    $plaintext .= "<br>Issued by: " . $server;
    $plaintext .= "<br>Usage: Badge request";
    $plaintext .= "<br>Badge text: (none)";
    $plaintext .= "<br>Badge color: (none)";
    $plaintext .= "<br>Badge type: 3";
    $plaintext .= "<br>Staff: NO";
    $plaintext .= "<br>Remote admin: NO";
    $plaintext .= "<br>Management: NO";
    $plaintext .= "<br>Overwatch mode: " . ($overwatchEnabled ? "YES" : "NO");
    $plaintext .= "<br>Global banning: NO";
    $plaintext .= "<br>Public key: " . base64_encode($_POST['publickey']);
    $plaintext .= "<br>Test signature: " . ($test ? "YES" : "NO");
    $plaintext .= "<br>Token Version: 2";
    echo $plaintext;

    openssl_sign($plaintext, $signature, $privatekey, OPENSSL_ALGO_SHA256);

    echo '<br>Signature: ' . base64_encode($signature);
}
else echo '-';