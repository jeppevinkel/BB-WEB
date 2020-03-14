<?php
$NoSessions = true;
$NoHeader = true;
$ApiPage = true;
$SkipCsrfValidation = true;
$DisableSessionValidation = true;
$LoadGlobal = true;
require_once '../../../config.php';
require_once '../../../vendor/autoload.php';
use GeoIp2\Database\Reader;

$server = $ServerName;

/*error_reporting(E_ALL);
ini_set('display_errors', '1');/**/

if (!isset($_POST['ticket'])) die('Missing ticket');
if (!isset($_POST['publickey'])) die('Missing public key hash');
$pattern='/^[A-Z0-9]+$/';

if (!preg_match($pattern, $_POST['ticket'])) die('Invalid ticket');
$fields = array('key' => $SteamworksDeveloperKey,
    'appid' => 700330,
    'ticket' => $_POST['ticket']);

$url = "https://api.steampowered.com/ISteamUserAuth/AuthenticateUserTicket/v1/?" . http_build_query($fields, '', "&");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

$SteamBan = "0";

$data = json_decode($response, true);
if ($data['response']['params']['result'] != "OK" || $data['response']['params']['result'] == null) {
    //invalid ticket. Lets try that again!
    $i = 0;
    while ($i < 3) {
        $i++;
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $response2 = curl_exec($ch2);
        curl_close($ch2);
        $data = json_decode($response2, true);
        if (isset($data['response']['params']) && $data['response']['params']['result'] == "OK") break;
    }
    if ($data['response']['params']['result'] != "OK" && $i >= 3) {
        echo "Valve servers are in maintenance";
        http_response_code(503);

        /*$ip = $_SERVER['REMOTE_ADDR'];
        if (strlen($ip) > 20) $ip = substr($ip, 0, 12) . '***';
        else $ip = substr($ip, 0, 7) . '***';

        $webhook = new Client('https://canary.discordapp.com/api/webhooks/556180242977259530/UzlfBH60O7gHBZH7BmqETcKH1dQcbTo82wSEcsGvAZxQGePhdNfgQ7bUSWXL7QtNxnPb');
        $embed = new Embed();
        $embed->title('Steam Auth API Error')->description('Auth request to steam failed (requestsignature.php). Returning 503 code.')->url('https://steamstat.us/')->thumbnail('https://i.imgur.com/hYIcRGX.jpg')->field('Response', '```json
' . $response . '```', 'true')->field('Server name', $ServerName)->field('IP Address', $ip)->color('008080');
        $webhook->username('Auth Script')->avatar('https://i.imgur.com/hYIcRGX.jpg')->embed($embed)->send();*/

        die();
    }
}

$userid = $data['response']['params']['steamid'] . '@steam';

$fields = array('key' => $SteamworksDeveloperKey,
    'steamids' => $data['response']['params']['steamid']);

$url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?" . http_build_query($fields, '', "&");
curl_setopt($ch, CURLOPT_URL, $url);
$responsenickname = curl_exec($ch);

$nicknamedata = json_decode($responsenickname, true);

$username = $nicknamedata['response']['players'][0]["personaname"];

$test = false;
$expiration = "+15 minutes";
$badgeResult = array();
$noBan = false;
$bypassGeo = false;
$bypassWL = false;
$bypass = false;
$overwatchEnabled = false;
$remoteadminEnabled = false;
$remoteadminEverywhere = false;
$globalbanningEnabled = false;
$ValidWithoutBadge = false;

$manager = false;
$globalMod = false;

if ($badgeResult != null) {
    if ($badgeResult['BadgeType'] != 0) $isStaff = true;
    $manager = $badgeResult['Management'] == 1;
    $globalMod = $badgeResult['GlobalBanning'] == 1;
}

//Check Security Clearances

if ($cache != null) {
    $checkPerms = json_decode($cache, true);
    if ($checkPerms['ValidWithoutBadge'] == 1) $ValidWithoutBadge = true;
    if (($ValidWithoutBadge || $isStaff)) {
        if ($checkPerms['BypassVAC'] == 1) $bypass = true;
        if ($checkPerms['BypassWL'] == 1) $bypassWL = true;
        if ($checkPerms['BypassGeoBlocks'] == 1) $bypassGeo = true;
        if ($checkPerms['BypassBans'] == 1) $noBan = true;
        if ($checkPerms['Overwatch'] == 1) $overwatchEnabled = true;
        if ($checkPerms['RemoteAdmin'] == 1) $remoteadminEnabled = true;
        else if ($checkPerms['RemoteAdmin'] == 2) {
            $remoteadminEnabled = true;
            $remoteadminEverywhere = true;
        }
    }
}

$IgnoreVACError = true; //Allows joining even with VAC errors

if (!$bypass) {
    curl_setopt($ch, CURLOPT_URL, "https://partner.steam-api.com/ICheatReportingService/StartSecureMultiplayerSession/v1/");
    $post = [
        'appid' => 700330,
        'key' => $SteamworksPublisherKey,
        'steamid' => $data['response']['params']['steamid']
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $server_output = curl_exec($ch);
    $vacdata = json_decode($server_output, true);
    $vacsession = "UNKNOWN";

    if ($vacdata['response']['success'] == true) {
        $vacsession = $vacdata['response']['session_id'];
        curl_setopt($ch, CURLOPT_URL, "https://partner.steam-api.com/ICheatReportingService/RequestVacStatusForUser/v1/");
        $post = [
            'appid' => 700330,
            'key' => $SteamworksPublisherKey,
            'steamid' => $data['response']['params']['steamid'],
            'session_id' => $vacsession
        ];
        $vacoutput = curl_exec($ch);
        $vacquery = json_decode($vacoutput, true);
        //if ($vacquery['response']['success'] == false) $SteamBan = "VAC Query Error (ERROR, NOT A BAN, CODE 2 - just restart steam or sth)";
        //else if ($vacquery['response']['session_verified'] == false) $SteamBan = "VAC Verification Error (ERROR, NOT A BAN - just restart steam or sth)";
        if ($IgnoreVACError == false) {
            if ($vacquery['response']['session_verified'] == false) $SteamBan = "VAC Verification Error (Please restart steam)";
            else if ($vacquery['response']['session_active'] == false) $SteamBan = "VAC Session not active (Please restart steam)";
        }
    } else if ($IgnoreVACError == false) $SteamBan = "VAC Error CODE 1 (Please Restart Steam)";
    curl_close($ch);
} else $vacsession = "NA";

//if ($nicknamedata['response']['players'][0]["personaname"] == "Koji!") $SteamBan = "Globally blacklisted IP range";

if (strpos(strtolower(cleanStringSpecialChars($nicknamedata['response']['players'][0]["personaname"])), 'zueyhack') !== false || strpos(strtolower(cleanStringSpecialChars($nicknamedata['response']['players'][0]["personaname"])), 'hackszuey') !== false || strpos(strtolower(cleanStringSpecialChars($nicknamedata['response']['players'][0]["personaname"])), 'hackzuey') !== false) {
    $SteamBan = "The IP you are connecting with is globally blocked"; //display ban message
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

if ($nicknamedata['response']['players'][0]["profilestate"] == null) {
    $profilestatenull = true;
}

$cbid = null;

if ($result != null) {
    $cbid = "2";
    $userid = "1234567890123456";
}

$privbeta = "";
if (isset($_POST['privatebeta'])) {
    $url = "https://partner.steam-api.com/ISteamUser/CheckAppOwnership/v2/?appid=859210&key=" . $SteamworksPublisherKey . "&steamid=" . $data['response']['params']['steamid'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    $chresult = curl_exec($ch);
    curl_close($ch);

    $privateBetaCheck = json_decode($chresult, true)["appownership"];
    $privbeta = $privateBetaCheck["ownsapp"] == true ? "YES" : "NO";
}

$plaintext = "User ID: " . $userid;
if ($cbid != null) $plaintext .= "<br>CBID: " . $cbid;
$plaintext .= "<br>Nickname: " . base64_encode($username);
$plaintext .= "<br>Request IP: " . $_SERVER['REMOTE_ADDR'];
$plaintext .= "<br>Global ban: " . $banned;
$plaintext .= "<br>Steam ban: " . $SteamBan;
$plaintext .= "<br>VAC session: " . $vacsession;
$plaintext .= "<br>Issuence time: " . gmdate("Y-m-d H:i:s");
$plaintext .= "<br>Expiration time: " . gmdate('Y-m-d H:i:s', strtotime($expiration));
$plaintext .= "<br>Issued by: " . $server;
$plaintext .= "<br>Usage: Authentication";
if (isset($_POST['DNT'])) $plaintext .= "<br>Do Not Track: YES";
$plaintext .= "<br>Bypass bans: " . ($noBan ? "YES" : "NO");
$plaintext .= "<br>Bypass geo restrictions: " . ($bypassGeo ? "YES" : "NO");
$plaintext .= "<br>Bypass WL: " . ($bypassWL ? "YES" : "NO");
if (strlen($privbeta) > 0) $plaintext .= "<br>Private beta ownership: " . $privbeta;
$plaintext .= "<br>Public key: " . base64_encode($_POST['publickey']);
$plaintext .= "<br>Test signature: " . ($test ? "YES" : "NO");
$plaintext .= "<br>Auth Version: 2";

echo $plaintext;

$signature ="";
openssl_sign($plaintext, $signature, $privatekey, OPENSSL_ALGO_SHA256);

echo '<br>Signature: ' . base64_encode($signature);
echo '<br>=== SECTION ===<br>';

if ($badgeResult != null) {
    $expiration = "+75 minutes";
    $plaintext = "User ID: " . $userid;
    $plaintext .= "<br>Nickname: " . base64_encode($username);
    $plaintext .= "<br>Issuence time: " . gmdate("Y-m-d H:i:s");
    $plaintext .= "<br>Expiration time: " . gmdate('Y-m-d H:i:s', strtotime($expiration));
    $plaintext .= "<br>Issued by: " . $server;
    $plaintext .= "<br>Usage: Badge request";
    $plaintext .= "<br>Badge text: " . $badgeResult['Text'];
    $plaintext .= "<br>Badge color: " . $badgeResult['Color'];
    $plaintext .= "<br>Badge type: " . $badgeResult['BadgeType'];
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
    $plaintext = ($version == 1 ? "Steam ID: " : "User ID: ") . $userid;
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
