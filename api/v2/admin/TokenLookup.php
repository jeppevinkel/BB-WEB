<?php
$NoSessions = true;
$NoHeader = true;
$ApiPage = true;
$SkipCsrfValidation = true;
$DisableSessionValidation = true;
require_once('../../../config.php');
require_once('../../../vendor/autoload.php');
use DiscordWebhooks\Client;
use DiscordWebhooks\Embed;
use Predis\Autoloader;

Predis\Autoloader::register();
if (!isset($_GET['token'])) {
    http_response_code(401);
    die('Missing argument (token)');
}
$clientLocal = new \Predis\Client([
    'scheme' => 'tcp',
    'host' => $RedisHost,
    'password' => $RedisPassword,
    'port' => $RedisPort - 1,
]);
$stmt = $pdo -> prepare("SELECT `InvalidApiTokens` FROM `RateLimits` WHERE `IP` = :IP LIMIT 1");
$stmt -> bindValue(':IP', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
$stmt -> execute();
$result = $stmt -> fetch();
$ipset = false;
if ($result != null) {
    $ipset = true;
    if ($result["InvalidApiTokens"] >= $RateLimitInvApiTokens) die("Rate limit exceeded");
}
$stmt = $pdo -> prepare("SELECT at.*, user.Banned, role.globalbans_query FROM `ApiTokens` as `at` INNER JOIN `Users` AS `user` ON at.UID = user.ID INNER JOIN `roles` AS `role` ON user.Role = role.ID WHERE `Token` = :token LIMIT 1");
$stmt -> bindValue(':token', $_GET['token'], PDO::PARAM_STR);
$stmt -> execute();
$row = $stmt -> fetch();
if ($row == null) {
    if ($ipset) $stmt = $pdo->prepare("UPDATE `RateLimits` SET `InvalidApiTokens` = `InvalidApiTokens` + 1 WHERE IP = :IP");
    else $stmt = $pdo->prepare("INSERT INTO  `RateLimits` (`IP`, `InvalidApiTokens`) VALUES (:IP, 1)");
    $stmt -> bindValue(':IP', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $stmt->execute();
    die('Invalid token');
}
if ($row['SteamQuery'] == 0) die('Access denied - token scope');
if ($row['Banned'] != null) die('Access denied - user banned');
if ($row['globalbans_query'] == 0) die('Access denied - token owner not permitted');
if (!isset($_GET['UserID'])) {
    http_response_code(400);
    die('Missing argument (UserID)');
}

$exp = explode("@", $_GET['UserID']);
if (sizeof($exp) == 1) die('Missing domain');
if (!is_numeric($exp[0])) die('ID must be a number');
if (!in_array($exp[1], $realdomains)) die("Invalid domain");

$return = array();
if ($exp[1] == '@steam') {
    $ch = curl_init();

    $fields = array('key' => $SteamworksDeveloperKey,
        'steamids' => $_GET['steamid']);

    $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?" . http_build_query($fields, '', "&");
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    $fields = array('key' => $SteamworksPublisherKey,
        'steamids' => $_GET['steamid']);

    $url = "https://api.steampowered.com/ISteamUser/GetPlayerBans/v1/?" . http_build_query($fields, '', "&");
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseBan = curl_exec($ch);

    curl_close($ch);

    $bansDecoded = json_decode($responseBan, true)["players"][0];

    if ($bansDecoded["NumberOfGameBans"] != 0 || $bansDecoded["CommunityBanned"] != "false" || $bansDecoded["EconomyBan"] != "none") $return["clean"] = false;
    else $return["clean"] = true;
    $return["sl-ban"] = false;

    if (isset($bansDecoded["bans"])) {
        $bans = $bansDecoded["bans"];
        foreach ($bans as $ban) {
            if ($ban["AppIdMin"] <= 700330 && $ban["AppIdMax"] >= 700330) {
                $return["sl-ban"] = true;
                $return["sl-ban-timestamp"] = gmdate("Y-m-d H:i:s", $ban["BanStartTime"]);
                $return["sl-ban-type"] = $ban["BanType"];
            }
        }
    }
    if (is_numeric($exp[0])) {
        $cacheKey = "query-" . $_GET['UserID'];
        $clientLocal->setex($cacheKey, 600, "-");

        $data = json_decode($response, true)["response"]["players"][0];
        $return["nickname"] = $data["personaname"];
        $return["realname"] = $data["realname"];
        $return["profile-url"] = $data["profileurl"];
        $return["avatar"] = $data["avatar"];
        $return["created"] = $data["timecreated"];
        $return["bans"] = $bansDecoded["NumberOfVACBans"];
        $return["publisher-bans"] = $bansDecoded["NumberOfGameBans"];
        $return["community-ban"] = $bansDecoded["CommunityBanned"];
        $return["economy-ban"] = $bansDecoded["EconomyBan"];

        echo json_encode($return);
    }
} else if ($exp[1] == '@discord') {

}