<?php
require_once 'vendor\autoload.php';
use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\EdmType;
$timezone = 'America/Chicago';
date_default_timezone_set($timezone);

$tableRestProxy     = ServicesBuilder::getInstance()->createTableService($_SERVER["CUSTOMCONNSTR_StorageAccount"]);
$tableName          = "noip";
$partition          = "dyndns";
$time               = date("Y-m-d h:i:s A");
$ip                 = $_SERVER['REMOTE_ADDR'];

if (isset($_GET['userid']))     $userid = $_GET['userid'];
if (isset($_GET['password']))   $password = $_GET['password'];
if (isset($_GET['hostname']))   $hostname = $_GET['hostname'];
if (isset($_GET['VPN']))        $VPN = $_GET['VPN'];

if (isset($userid)) {
    // First, we change it to something else, so we trigger the update in no-IP
    $xml = file_get_contents("http://$userid:$password@dynupdate.no-ip.com/nic/update?hostname=$hostname&myip=1.1.1.1");
    // Then we set it to what it should be
    $xml = file_get_contents("http://$userid:$password@dynupdate.no-ip.com/nic/update?hostname=$hostname&myip=$ip");
    
    // Create table, if needed
    try {
        $tableRestProxy->createTable($tableName);
        }
    catch(ServiceException $e){
        $code = $e->getCode();
    }

    // determine if we have seen this host before
    $resultcount = 0;
    try {
        $result = $tableRestProxy->getEntity($tableName, $partition, $hostname);
        $resultcount = 1;
    }
    catch(ServiceException $e){
    }

    if ($resultcount == 0) {
        // This is our first time to see this device
        $count = 1;    
        
        $entity = new Entity();
        $entity->setPartitionKey($partition);
        $entity->setRowKey($hostname);
        $entity->addProperty("userID", null, $userid);
        $entity->addProperty("password", null, $password);
        $entity->addProperty("count", null, "$count");
        $entity->addProperty("IPAddress", null, $ip);
        $entity->addProperty("VPN", null, "0");
        $entity->addProperty("Date", null, $time);
        $entity->addProperty("Result", null, $xml);

        try{
            $tableRestProxy->insertEntity($tableName, $entity);
        }
        catch(ServiceException $e){
            $code = $e->getCode();
            $error_message = $e->getMessage();
        }
    }  else {
        // we have seen this device before
        $count = 0;    
        try {
            $count = intval($tableRestProxy->getEntity($tableName, $partition, $hostname)->getEntity()->getProperty("count")->getValue());
        } catch (ServiceException $e) {
           $code = $e->getCode();
        }
        $count = $count + 1;

        $result = $tableRestProxy->getEntity($tableName, $partition, $hostname);

        $entity = $result->getEntity();
        $entity->setPropertyValue("userID", $userid);
        $entity->setPropertyValue("password", $password);
        $entity->setPropertyValue("count", "$count");
        $entity->setPropertyValue("IPAddress", $ip);
        $entity->setPropertyValue("VPN", "0");
        $entity->setPropertyValue("Date", $time);
        $entity->setPropertyValue("Result", $xml);
        
        $tableRestProxy->updateEntity($tableName, $entity);
    }
     echo $xml;

} elseif (isset($VPN)) {
    // We are updating the VPN connection count only
    // because we call this function right after our update IP function, we need to sleep 3 seconds
    sleep(5);
    $result = $tableRestProxy->getEntity($tableName, $partition, $hostname);

    $entity = $result->getEntity();
    $entity->setPropertyValue("VPN", "$VPN");
    
    $tableRestProxy->updateEntity($tableName, $entity);
    }
    
else {

    // retrieve the entry
    $filter = "PartitionKey eq '$partition'";
    try {
        $result = $tableRestProxy->queryEntities($tableName, $filter);
    }
    catch(ServiceException $e){
        $code = $e->getCode();
        $error_message = $e->getMessage();
        echo $code.": ".$error_message."<br />";
    }

    $entities = $result->getEntities();

    //echo geoip_isp_by_name($ip);

    echo "Using Azure Tables.  Using storage account: ". explode(";", $_SERVER["CUSTOMCONNSTR_StorageAccount"])[1]."</p>";
    echo "<table cellpadding=5><tr align=center><td><b>User ID</b></td><td><b>Host Name</b></td><td><b>Updates</b></td><td><b>IP Address</b></td><td><b>VPNs</b></td><td><b>Date / Time</b></td><td><b> DDNS Result</b></td></tr>";

    foreach($entities as $entity){
        echo "<tr>";
        echo "<td>".$entity->getProperty("userID")->getValue()."</td>";
        echo "<td>".$entity->getRowKey()."</td>";
        echo "<td align=right>".$entity->getProperty("count")->getValue()."</td>";
        echo "<td align=right>".$entity->getProperty("IPAddress")->getValue()."</td>";
        echo "<td align=right>".$entity->getProperty("VPN")->getValue()."</td>";
        echo "<td align=right>".$entity->getProperty("Date")->getValue()."</td>";
        echo "<td>".$entity->getProperty("Result")->getValue()."</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</p>You are $ip and the time is $time $timezone.";
    //echo geoip_country_code_by_name("www.msn.com");
    //echo geoip_database_info(GEOIP_COUNTRY_EDITION);
    //echo phpinfo();

}

?>

<!DOCTYPE html>
<html lang="en">
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
    </body>
</html>
