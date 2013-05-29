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

$rackspace = new \StringsLoadbalancer\RackspaceLoadBalancerDriver($providerConnection);

/*
//Create
$nodes = array(
    array(
        'ip' => '10.181.27.121',
        'port' => '80'
    )
);
print_r($rackspace->create('test','HTTP','80','RANDOM','private',$nodes));
*/


/*
//Delete
$lbId=140553;
$rackspace->delete($lbId);
*/

//Add Node
/*
$lbId=140555;
$node = array(
    'ip' => '10.181.27.122',
    'port' => '232'
);
print_r($rackspace->addNode($lbId,$node));
*/


//Remove Node
$lbId=140555;
$nodeId=293637;
$rackspace->removeNode($lbId,$nodeId);
