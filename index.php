<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0"/>
    <title>Southwood - Error</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/error.css" type="text/css" rel="stylesheet" media="screen,projection">
</head>
<body>
<div id="errorbox">
    <div id="cnt">
        <div class="logo">
            <img src="../images/southwoodlogo.png" height="256" width="256">
        </div>
        <div class="content">
            <h1>API Interface</h1><br>
            Welcome to Southwood Studio's API interface.
        </div>
    </div>
</div>

<div id ="footer" class="row navbar-fixed-bottom">
    <div class="col-md-11" id="copyright">Requested by <?php echo $_SERVER["HTTP_CF_CONNECTING_IP"]; ?></div>
</div>
</body>