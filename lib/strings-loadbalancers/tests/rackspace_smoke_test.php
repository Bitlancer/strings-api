<?php

require('../Autoload.php');

$connDetails = array(
    'region' => 'DFW',
    'identityApiEndpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
    'credentials' => array(
        'username' => 'bitlancer3',
        'secret' => '3d0baf0e7b9c4882aefadaf0837ce61b'
    )
);

$conn = new \StringsLoadBalancer\RackspaceLoadBalancerDriver($connDetails);


//Create the LB
$result = $conn->create(array(
    'name' => 'Test',
    'algorithm' => 'RANDOM',
    'protocol' => 'HTTP',
    'port' => 80,
    'virtualIps' => array(
        array(
            'type' => 'PUBLIC'
        )
    )
));

$lbId = $result['id'];

$status = waitForActive($conn, $lbId);
if($status != 'ACTIVE')
    throw new \UnexpectedValueException("Unexpected load-balancer status $status");

$result = $conn->get($lbId);
$actualAttrs = array(
    'name' => $result['name'],
    'algorithm' => $result['algorithm'],
    'protocol' => $result['protocol'],
    'port' => $result['port']
);
$expectedAttrs = array(
    'name' => 'Test',
    'algorithm' => 'RANDOM',
    'protocol' => 'HTTP',
    'port' => '80'
);
if(!empty(array_diff($actualAttrs, $expectedAttrs))){
    throw new \Exception('Load-balancer attributes do not match.');
}


//Update the LB
$newAttrs = array(
    'name' => 'Test-updated',
    'algorithm' => 'LEAST_CONNECTIONS',
    'protocol' => 'HTTPS',
    'port' => '443'
);
$result = $conn->update($lbId, $newAttrs);

$status = waitForActive($conn, $lbId);
if($status != 'ACTIVE')
    throw new \UnexpectedValueException("Unexpected load-balancer status $status");

$result = $conn->get($lbId);
$actualAttrs = array(
    'name' => $results['name'],
    'algorithm' => $results['algorithm'],
    'protocol' => $results['protocol'],
    'port' => $results['port']
);
$expectedAttrs = $newAttrs;
if(!empty($array_diff($actualAttrs, $expectedAttrs))){
    throw new \Exception('Load-balancer Attributes do not match.');
}


//Add nodes
$result = $conn->addNodes($lbId, array(
    array(
        'address' => '10.7.7.1',
        'port' => 80,
        'condition' => 'ENABLED'
    ),
    array(
        'address' => '10.7.7.2',
        'port' => 80,
        'condition' => 'ENABLED'
    ),
    array(
        'address' => '10.7.7.3',
        'port' => 80,
        'condition' => 'ENABLED'
    )
));

$status = waitForActive($conn, $lbId);
if(!$status != 'ACTIVE')
    throw new \UnexpectedValueException("Unexpected load-balancer status $status");


//Get nodes
$result = $conn->getNodes($lbId);
print_r($result);


//Remove Nodes
$nodes = $conn->getNodes($lbId);


//Delete the LB
$conn->delete($lbId);


function waitForActive($conn, $loadBalancerId, $pollInterval=10){

    $status = false;

    while(true){
        $result = $conn->get($loadBalancerId);
        $status = $result['loadBalancer']['status'];

        if($status == 'ACTIVE')
            break;
        if($status == 'ERROR')
            break;
        else
            sleep($pollInterval); 
    }

    return $status;
}
