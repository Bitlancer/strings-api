#!/usr/bin/php
<?php

require 'Autoload.php';

$providerConnection = array(
	'region' => 'DFW',
    'identityApiEndpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
    'credentials' => array(
        'username' => 'bitlancer3',
        'secret' => ''
    )
);

$rackspace = new \StringsInfrastructure\RackspaceInfrastructureDriver($providerConnection);

//Get an image
$image = 'da1f0392-8c64-468f-a839-a9e56caebf07';    //Centos

//Get a flavor
$flavor = '2';  //512 MB

//Create a server - wait for completion
/*
echo "Creating server...\t";
$server = $rackspace->createServer('Test2',$flavor,$image,true,600);
echo "done\n";
*/

$server = array(
	'id' => '5599d276-d233-474a-99e0-a9a74cc9b25c'
);

$serverId = $server['id'];
echo "Server Id = $serverId\n";

//Get server status
$rackspace->getServerIPs($serverId);

//Resize server
/*
echo "Resizing server...\t";
$rackspace->resizeServer($serverId,3,true,3);
echo "done\n";
*/

//Rebuild server
/*
echo "Rebuilding server...\t";
$rackspace->rebuildServer($serverId,3,92ac9889-2ede-4633-ab39-25560436d83a);
echo "done\n";
*/

//Delete server
/*
$rackspace->deleteServer($serverId);

//Get server status
echo "Status...\t" . $rackspace->getServerStatus($serverId) . "\n";
*/
