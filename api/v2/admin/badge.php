<?php
$NoSessions = true;
$NoHeader = true;
$ApiPage = true;
$SkipCsrfValidation = true;
$DisableSessionValidation = true;
$LoadGlobal = true;
require_once('../../../config.php');
if (!$ServerPrimary) die("Function disabled on backup server.");
if (!isset($_POST['token'])) {
    http_response_code(401);
    die('Missing argument (token)');
}
$stmt = $pdo -> prepare("SELECT `InvalidApiTokens` FROM `RateLimits` WHERE `IP` = :IP LIMIT 1");
$stmt -> bindValue(':IP', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
$stmt -> execute();
$result = $stmt -> fetch();
$ipset = false;
if ($result != null) {
    $ipset = true;
    if ($result["InvalidApiTokens"] >= $RateLimitInvApiTokens) {
        http_response_code(403);
        die("Rate limit exceeded");
    }
}
$stmt = $pdo -> prepare("SELECT at.*, user.Banned, user.Role, role.badges_manage, role.badges_query FROM `ApiTokens` as `at` INNER JOIN `Users` AS `user` ON at.UID = user.ID INNER JOIN `roles` AS `role` ON user.Role = role.ID WHERE `Token` = :token LIMIT 1");
$stmt -> bindValue(':token', $_POST['token'], PDO::PARAM_STR);
$stmt -> execute();
$row = $stmt -> fetch();
if ($row == null) {
    if ($ipset) $stmt = $pdo->prepare("UPDATE `RateLimits` SET `InvalidApiTokens` = `InvalidApiTokens` + 1 WHERE IP = :IP");
    else $stmt = $pdo->prepare("INSERT INTO  `RateLimits` (`IP`, `InvalidApiTokens`) VALUES (:IP, 1)");
    $stmt -> bindValue(':IP', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $stmt->execute();
    http_response_code(401);
    die('Invalid token');
}
if ($row['BadgeManagement'] == 0){
    http_response_code(401);
    die('Access denied - token scope');
}
if ($row['Banned'] != null) {
    http_response_code(401);
    die('Access denied - user banned');
}
if (!isset($_POST['action'])) {
    http_response_code(400);
    die('Missing argument (action)');
}

if ($_POST['action'] == "issue") {
    if (!isset($_POST['id']) || strlen($_POST['id']) == 0) {
        http_response_code(400);
        die('Missing argument (id)');
    }

    $exp = explode("@", $_POST['id']);
    if (sizeof($exp) == 1) {
        http_response_code(400);
        die('Missing domain');
    }
    if (!is_numeric($exp[0])) {
        http_response_code(400);
        die('ID must be a number');
    }
    if (!in_array($exp[1], $realdomains)) {
        http_response_code(400);
        die("Invalid domain");
    }

    $stmt = $pdoGlobal->prepare("SELECT `Badge`, `Description` FROM staff_global.PlayerBadges WHERE `UserID` = :sid LIMIT 1");
    $stmt->bindValue(':sid', $_POST['id'], PDO::PARAM_INT);
    $stmt->execute();
    $curr = $stmt->fetch();
    if ((!isset($_POST['badge']) || strlen($_POST['badge']) == 0) && $curr == null) die("Nothing to delete");

    if ($row['badges_manage'] == 0) {
        if ($curr != null) {
            $stmt = $pdo->prepare("SELECT `ID` FROM `GroupsBadges` WHERE `GroupID` = :group AND `BadgeID` = :badge LIMIT 1");
            $stmt->bindValue(':group', $row['Role'], PDO::PARAM_INT);
            $stmt->bindValue(':badge', $curr['Badge'], PDO::PARAM_INT);
            $stmt->execute();
            $desc = $stmt->fetch();
            if ($desc == null) {
                http_response_code(401);
                die("You don't have permissions to revoke current badge.");
            }
        }

        if (isset($_POST['badge']) && strlen($_POST['badge']) != 0) {
            $stmt = $pdo->prepare("SELECT `ID` FROM `GroupsBadges` WHERE `GroupID` = :group AND `BadgeID` = :badge LIMIT 1");
            $stmt->bindValue(':group', $row['Role'], PDO::PARAM_INT);
            $stmt->bindValue(':badge', $_POST['badge'], PDO::PARAM_INT);
            $stmt->execute();
            $desc = $stmt->fetch();
            if ($desc == null) {
                http_response_code(401);
                die("You don't have permissions to issue that badge.");
            }
        }
    }

    if (!isset($_POST['badge']) || strlen($_POST['badge']) == 0)
        AddToLogAnonFull($row["UID"], 5, $_POST['id'], "", $curr['Description'], "", "", "", $_POST['badge'], $row["ID"]);
    else AddToLogAnonFull($row["UID"], 5, $_POST['id'], $_POST['badge'], $_POST['info'], "", "", "", $_POST['badge'], $row["ID"]);

    if (empty($_POST['info2']) && isset($_POST['badge']) && strlen($_POST['badge']) > 0) {
        http_response_code(400);
        die('Missing Argument (DiscordID)');
    }

    $stmt = $pdoGlobal -> prepare("DELETE FROM staff_global.PlayerBadges WHERE `UserID` = :userid");
    $stmt -> bindValue(':userid', $_POST['id'], PDO::PARAM_INT);
    $stmt -> execute();

    if (isset($_POST['badge']) && strlen($_POST['badge']) > 0) {
        if (!isset($_POST['info']) || strlen($_POST['info']) == 0) die('Missing argument (info)');
        if (empty($_POST['info2']) && isset($_POST['badge']) && strlen($_POST['badge']) > 0) die('Missing Argument (DiscordID)');
        $stmt = $pdoGlobal->prepare("INSERT INTO staff_global.PlayerBadges (`UserID`, `Badge`, `Description`, `Description2`) VALUES (:userid, :badge, :description, :descsecond)");
        $stmt->bindValue(':userid', $_POST['id'], PDO::PARAM_INT);
        $stmt->bindValue(':badge', $_POST['badge'], PDO::PARAM_INT);
        $stmt->bindValue(':description', $_POST['info'], PDO::PARAM_STR);
        if (isset($_POST['info2']) && strlen($_POST['info2']) > 0) $stmt->bindValue(':descsecond', $_POST['info2'], PDO::PARAM_STR);
        else $stmt->bindValue(':descsecond', null, PDO::PARAM_STR);
        $stmt->execute();
    }

    echo 'Done';
} else if ($_POST['action'] == "query") {
    if ($row['badges_query'] == 0) {
        http_response_code(401);
        die('Access denied - token owner not permitted');
    }
    if (!isset($_POST['id']) || strlen($_POST['id']) == 0) {
        http_response_code(400);
        die('Missing argument (id)');
    }
    $exp = explode("@", $_POST['id']);
    if (sizeof($exp) == 1) {
        http_response_code(400);
        die('Missing domain');
    }
    if (!is_numeric($exp[0])) {
        http_response_code(400);
        die('ID must be a number');
    }
    if (!in_array($exp[1], $realdomains)) {
        http_response_code(400);
        die("Invalid domain");
    }

    $stmt = $pdoGlobal -> prepare("SELECT badge.Badge, badge.Description, badge.Description2, badges.Text FROM staff_global.PlayerBadges AS `badge` LEFT OUTER JOIN Badges AS badges on badge.Badge = badges.ID WHERE `UserID` = :userid LIMIT 1");
    $stmt -> bindValue(':userid', $_POST['id'], PDO::PARAM_INT);
    $stmt -> execute();
    $row = $stmt -> fetch();
    if ($row == null) die("Badge not issued");
    $data = ["badge" => $row['Badge'], "text" => $row['Text'], "info" => $row['Description'], "info2" => $row['Description2']];
    echo json_encode($data);
} else if ($_POST['action'] == "queryDiscordId") {
    if ($row['badges_query'] == 0) {
        http_response_code(401);
        die('Access denied - token owner not permitted');
    }
    if (!isset($_POST['id']) || strlen($_POST['id']) == 0) {
        http_response_code(400);
        die('Missing argument (id)');
    }
    if (!is_numeric($_POST['id'])) {
        http_response_code(400);
        die('ID must be a number');
    }
    $stmt = $pdoGlobal -> prepare("SELECT badge.UserID, badge.Badge, badge.Description, badge.Description2, badges.Text FROM staff_global.PlayerBadges AS `badge` LEFT OUTER JOIN Badges AS badges on badge.Badge = badges.ID WHERE badge.Description2 = :id LIMIT 1");
    $stmt -> bindValue(':id', $_POST['id'], PDO::PARAM_INT);
    $stmt -> execute();
    $row = $stmt -> fetch();
    if ($row == null) die("Badge not issued");
    $data = ["userid" => $row["UserID"], "badge" => $row['Badge'], "text" => $row['Text'], "info" => $row['Description'], "info2" => $row['Description2']];
    echo json_encode($data);
} else if ($_POST['action'] == "list") {
    if ($row['badges_query'] == 0) {
        http_response_code(401);
        die('Access denied - token owner not permitted');
    }
    $stmt = $pdoGlobal -> prepare("SELECT badge.UserID, badge.Badge, badge.Description, badge.Description2, badges.Text FROM staff_global.PlayerBadges AS `badge` LEFT OUTER JOIN Badges AS badges on badge.Badge = badges.ID ORDER BY badge.Badge ASC, badge.UserID ASC");
    $stmt -> execute();
    $alldata = [];
    $i = 0;
    while ($row = $stmt -> fetch()) {
        $data = ["userid" => $row["UserID"], "badge" => $row['Badge'], "text" => $row['Text'], "info" => $row['Description'], "info2" => $row['Description2']];
        $alldata += [$i => $data];
        $i++;
    }
    echo json_encode($alldata);
} else {
    http_response_code(400);
    echo "Unknown action";
}
