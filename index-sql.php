<html>
<head>
<style type="text/css" media="screen">

table{
border-collapse:collapse;
border:1px solid #FF0000;
}

table td{
border:1px solid #FF0000;
}
</style>
</head>
<body>

<?php
    
$DefaultConnection = explode(";", $_SERVER["SQLAZURECONNSTR_DefaultConnection"]);
$serverName = explode("=", $DefaultConnection[0])[1];
$connectionInfo = array("Database"=>explode("=", $DefaultConnection[1])[1],"UID"=>explode("=", $DefaultConnection[2])[1], "PWD"=>explode("=", $DefaultConnection[3])[1]);

$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( !$conn ) {
     echo "Connection could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}

$ip = $_SERVER['REMOTE_ADDR'];
if (isset($_GET['userid'])) $userid = $_GET['userid'];
if (isset($_GET['password'])) $password = $_GET['password'];
if (isset($_GET['hostname'])) $hostname = $_GET['hostname'];

if (isset($_GET['userid'])) {
    // First, we change it to something else, so we trigger the update in no-IP
    $xml = file_get_contents("http://$userid:$password@dynupdate.no-ip.com/nic/update?hostname=$hostname&myip=1.1.1.1");
    // Then we set it to what it should be
    $xml = file_get_contents("http://$userid:$password@dynupdate.no-ip.com/nic/update?hostname=$hostname&myip=$ip");

	echo $xml;

        $sql = "delete from [no-ip] where [hostname] = '" . $hostname . "'";
		$params = array();

		$stmt = sqlsrv_query( $conn, $sql, $params);
		if( $stmt === false ) {
		     die( print_r( sqlsrv_errors(), true));
		}

		$sql = "INSERT INTO [no-ip] ([userid],[hostname],[ipaddress],[result])VALUES(?,?,?,?)";
		$params = array($userid, $hostname, $ip,$xml);

		$stmt = sqlsrv_query( $conn, $sql, $params);
		if( $stmt === false ) {
		     die( print_r( sqlsrv_errors(), true));
		}

	} else {

	$sql = "SELECT [userid],[hostname],[ipaddress],[result],convert(varchar(25), [dateandtime], 120) as [dateandtime] FROM [no-ip]";
	$params = array();
	$options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );
	$stmt = sqlsrv_query( $conn, $sql , $params, $options );

	echo "<table><tr><td><b>User ID</b></td><td><b>Host Name</b></td><td><b>IP Address</b></td><td><b>Date / Time</b></td><td><b>Result</b></td></tr>";
	while($row = sqlsrv_fetch_array($stmt)) {
	      echo "<tr><td>" . $row['userid'] . "</td><td>" . $row['hostname']. "</td><td>" . $row['ipaddress'] . "</td><td>" . $row['dateandtime'] . "</td><td>" .$row['result'] . "</td></tr>";
		}
	echo "</table>";

		$sql = "select convert(varchar(25), getdate(), 120) as [dateandtime]";
		$params = array();
		$options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );
		$stmt = sqlsrv_query( $conn, $sql , $params, $options );

		$row = sqlsrv_fetch_array($stmt);
		$currenttime = $row['dateandtime'];
		echo "Current Date / Time: " . $currenttime . ", you are: " . $_SERVER['REMOTE_ADDR'];

	}

?>
</body>
</html>


