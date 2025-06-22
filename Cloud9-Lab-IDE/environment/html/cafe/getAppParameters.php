<?php

require 'AWSSDK/aws.phar';

$az = file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone');
$region = substr($az, 0, -1);

$ssm_client = new Aws\Ssm\SsmClient([
    'version' => 'latest',
    'region'  => $region
]);

$result = $ssm_client->GetParametersByPath(['Path' => '/cafe']);

$showServerInfo = "false";
$timeZone = "America/New_York";
$currency = "$";
$db_url = "localhost";
$db_name = "cafe_db";
$db_user = "root";
$db_password = "Re:Start!9";

?>
