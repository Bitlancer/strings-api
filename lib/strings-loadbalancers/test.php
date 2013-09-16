#!/usr/bin/php
<?php

require 'Autoload.php';

$providerConnection = array(
	'region' => 'DFW',
    'identityApiEndpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
    'credentials' => array(
        'username' => 'bitlancer3',
        'secret' => '3d0baf0e7b9c4882aefadaf0837ce61b'
    )
);

$rackspace = new \StringsLoadbalancer\RackspaceLoadBalancerDriver($providerConnection);


//Create
/*
$nodes = array(
    array(
        'ip' => '10.181.27.121',
        'port' => '80'
    )
);
print_r($rackspace->create('test',array('name' => 'HTTP','port' => '80'),'RANDOM','public',$nodes));
*/

//Add Node
$lbId=211331;
$node = array(
    'ip' => '10.181.27.122',
    'port' => '232'
);
print_r($rackspace->addNode($lbId,$node));


//Remove Node
/*
$lbId=140555;
$nodeId=293637;
$rackspace->removeNode($lbId,$nodeId);
*/
