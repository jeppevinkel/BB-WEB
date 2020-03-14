<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0"/>
    <title>Southwood - API</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/error.css" type="text/css" rel="stylesheet" media="screen,projection">
    <link rel="icon" href="/images/logo.ico">
</head>
<body>
<div class="content">
    <h1>API Interface</h1><br>
    Welcome to Southwood Studio's API interface.<br>
    <div class="list-group index-list">
        <a class="list-group-item list-group-item-action" role="button" href="/serverlist">Serverlist</a>
    </div>
</div>

<div id ="footer" class="row navbar-fixed-bottom">
    <div class="col-md-11" id="requestFrom">Requested by <?php echo $_SERVER["HTTP_CF_CONNECTING_IP"]; ?></div>
</div>
</body>