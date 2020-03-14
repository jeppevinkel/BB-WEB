<?php
$NoSessions = true;
$NoHeader = true;
$ApiPage = true;
$SkipCsrfValidation = true;
$DisableSessionValidation = true;
$LoadGlobal = true;
/*error_reporting(E_ALL);
ini_set('display_errors', '1');/**/
require_once('../../config.php');
require '../../vendor/autoload.php';
use DiscordWebhooks\Client;
use DiscordWebhooks\Embed;

if (!isset($_POST['token'])) die('Missing token');
$stmt = $pdo -> prepare("SELECT `InvalidApiTokens` FROM `RateLimits` WHERE `IP` = :IP LIMIT 1");
$stmt -> bindValue(':IP', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
$stmt -> execute();
$result = $stmt -> fetch();
$ipset = false;
if ($result != null) {
    $ipset = true;
    if ($result["InvalidApiTokens"] >= $RateLimitInvApiTokens) die("Rate limit exceeded");
}
$stmt = $pdo -> prepare("SELECT at.*, user.Banned, role.globalbans_manage, role.globalbans_query FROM `ApiTokens` as `at` INNER JOIN `Users` AS `user` ON at.UID = user.ID INNER JOIN `roles` AS `role` ON user.Role = role.ID WHERE `Token` = :token LIMIT 1");
$stmt -> bindValue(':token', $_POST['token'], PDO::PARAM_STR);
$stmt -> execute();
$row = $stmt -> fetch();
if ($row == null) {
    if ($ipset) $stmt = $pdo->prepare("UPDATE `RateLimits` SET `InvalidApiTokens` = `InvalidApiTokens` + 1 WHERE IP = :IP");
    else $stmt = $pdo->prepare("INSERT INTO  `RateLimits` (`IP`, `InvalidApiTokens`) VALUES (:IP, 1)");
    $stmt -> bindValue(':IP', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $stmt->execute();
    die('Invalid token');
}
if ($row['GlobalBanning'] == 0) die('Access denied - token scope');
if ($row['Banned'] != null) die('Access denied - user banned');
//if ($row['globalbans_manage'] == 0) die('Access denied - token owner not permitted');
if (!isset($_POST['action'])) die('Missing action');
if (!isset($_POST['userid'])) die('Missing "userid" parameter');

$exp = explode("@", $_POST['userid']);
if (sizeof($exp) == 1) die('Missing domain');
if (!is_numeric($exp[0])) die('ID must be a number');
if (!in_array($exp[1], $authdomains)) die("Invalid domain");

if ($_POST['action'] == "ban") {
    if ($row['globalbans_manage'] == 1 AND $row['globalbans_query'] == 1) {
        $stmt = $pdoGlobal->prepare("INSERT INTO `GlobalBans` (`UserID`, `Reason`, `Issuer`, `IssuanceTime`, `Steamban`, `Expires`) VALUES (:userid, '1', :uid, :timestamp, '1', '9999-12-30')");
        $stmt->bindValue(':userid', $_POST['userid'], PDO::PARAM_INT);
        $stmt->bindValue(":uid", $row["UID"], PDO::PARAM_INT);
        $stmt->bindValue(":timestamp", gmdate("Y-m-d H:i:s"), PDO::PARAM_STR);
        $stmt->execute();
        AddToLogAnonFull($row["UID"], 3, $_POST['userid'], "", "", "", "", "", "", $row['ID']);
        $ch = curl_init();

        if ($exp[1] == "steam") {
            curl_setopt($ch, CURLOPT_URL, "https://partner.steam-api.com/ICheatReportingService/ReportPlayerCheating/v1/");
            $post = [
                'appid' => 700330,
                'key' => $SteamworksPublisherKey,
                'steamid' => $_POST['steamid'],
                'playerreport' => 'true',
            ];
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $server_output = curl_exec($ch);
            $data = json_decode($server_output, true);
            $reportid = $data['response']['reportid'];
            curl_setopt($ch, CURLOPT_URL, "https://partner.steam-api.com/ICheatReportingService/RequestPlayerGameBan/v1/");
            $post = [
                'appid' => 700330,
                'key' => $SteamworksPublisherKey,
                'steamid' => $_POST['steamid'],
                'reportid' => $reportid,
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $dta = curl_exec($ch);
            curl_close($ch);
        }
        echo "Banned";
        die();
    } else if ($row['globalbans_query'] == 1 AND $row['globalbans_manage'] == 0) {
        $stmt = $pdoGlobal->prepare("SELECT `UserID` FROM `ApprovalQueue` WHERE `UserID` = :userid LIMIT 1");
        $stmt->bindValue(':userid', $_POST['userid'], PDO::PARAM_INT);
        $stmt->execute();
        $rows_found = $stmt->rowCount();
        if ($rows_found > 0) {
            die('Banned'); // Actually not but uh, else the client will think the server is erroring.
        }

        $stmt = $pdoGlobal->prepare("INSERT INTO `GlobalBans` (`UserID`, `Reason`, `Expires`, `Issuer`, `IssuanceTime`, `Steamban`, `Proof`) VALUES (:userid, '1', '9999-12-30', :uid, :timestamp, '1', NULL);");
        $stmt->bindValue(':userid', $_POST['userid'], PDO::PARAM_INT);
        $stmt->bindValue(":uid", $row["UID"], PDO::PARAM_INT);
        $stmt->bindValue(":timestamp", gmdate("Y-m-d H:i:s"), PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $pdoGlobal->prepare("INSERT INTO `ApprovalQueue` (`SteamID`, `Reason`, `Issuer`, `IssuanceTime`, `Steamban`, `Proof`) VALUES (:userid, 1, :uid, :timestamp, '1', NULL)");
        $stmt->bindValue(':userid', $_POST['userid'], PDO::PARAM_INT);
        $stmt->bindValue(":uid", $row["UID"], PDO::PARAM_INT);
        $stmt->bindValue(":timestamp", gmdate("Y-m-d H:i:s"), PDO::PARAM_STR);
        $stmt->execute();
        echo "Banned";
        AddToLogAnonFull($row["UID"], 18, $_POST['userid'], "", "", "", "", "", "", $row['ID']);

        $stmt = $pdo->prepare("SELECT `Login` FROM `Users` WHERE `ID` = :uid LIMIT 1");
        $stmt->bindValue(":uid", $row["UID"], PDO::PARAM_INT);
        $stmt->execute();
        $resultID = $stmt->fetch();

        $webhook = new Client('https://canary.discordapp.com/api/webhooks/499662068774076494/OH7kRlF1z00aaTQHNF56ECwtgwoNLnGGwSUgXbg16C-33QvCnFeG2qDkr1V-B85rtd9K');
        $embed = new Embed();
        $embed->title('New Ban Report')->description('A new ban report has been made!')->url('https://staff.scpslgame.com')->thumbnail('https://i.imgur.com/nNnTQsc.png')->field('UserID', $_POST['userid'],'true')->field('Proof/Reason', 'Not Yet Added', 'true')->field('Ban Report By', $resultID['Login'])->color('ff0000');
        $webhook->username('SecurityBot')->avatar('https://i.imgur.com/h6V8lg7.png')->message('<@&472792573203972096>')->embed($embed)->send();

        die();

    } else echo "Unknown Error";
}
else echo "Unknown action";
