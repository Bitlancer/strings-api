<?php

require('../Autoload.php');

$tester = new TestLoadBalancer();
$tester->runTests();

class TestLoadBalancer {

    public function __construct(){

        $connDetails = array(
            'region' => 'DFW',
            'identityApiEndpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
            'credentials' => array(
                'username' => '',
                'secret' => ''
            )
        );

        $this->connection = new \StringsLoadBalancer\RackspaceLoadBalancerDriver($connDetails);
    }

    public function runTests(){

        $lbId = $this->testCreate();
        $this->testUpdate($lbId);
        $this->testNodes($lbId);
        $this->testDelete($lbId);
    }

    private function testCreate(){

        //Create the LB
        $result = $this->connection->create(array(
            'name' => 'Test',
            'algorithm' => 'RANDOM',
            'protocol' => 'HTTP',
            'port' => 80,
            'virtualIps' => array(
                array(
                    'type' => 'PUBLIC'
                )
            ),
            'sessionPersistence' => array(
                'persistenceType' => 'HTTP_COOKIE'
            )
        ));

        $lbId = $result['id'];

        $status = $this->connection->waitForactive($lbId);
        if($status != 'active')
            throw new \UnexpectedValueException("Unexpected load-balancer status $status");

        $result = $this->connection->get($lbId);
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
        $diff = array_diff($actualAttrs, $expectedAttrs);
        if(!empty($diff)){
            throw new \Exception('Load-balancer attributes do not match.');
        }

        return $lbId;
    }

    private function testUpdate($lbId){

        //Update the LB
        $newAttrs = array(
            'name' => 'Test-updated',
            'algorithm' => 'LEAST_CONNECTIONS',
            'protocol' => 'HTTPS',
            'port' => '443'
        );
        $result = $this->connection->update($lbId, $newAttrs);

        $status = $this->connection->waitForactive($lbId);
        if($status != 'active')
            throw new \UnexpectedValueException("Unexpected load-balancer status $status");

        //Check std attributes
        $result = $this->connection->get($lbId);
        $actualAttrs = array(
            'name' => $result['name'],
            'algorithm' => $result['algorithm'],
            'protocol' => $result['protocol'],
            'port' => $result['port']
        );
        $expectedAttrs = $newAttrs;
        $diff = array_diff($actualAttrs, $expectedAttrs);
        if(!empty($diff)){
            $diffStr = print_r($diff, true);
            throw new \Exception("Load-balancer Attributes do not match: $diffStr");
        }

    }

    private function testNodes($lbId){

        $node = array(
            'address' => '10.181.9.204',
            'port' => 80,
            'condition' => 'ENABLED'
        );

        //Add nodes
        $result = $this->connection->addNodes($lbId, array(
            $node,
        ));

        $status = $this->connection->waitForActive($lbId);
        if($status != 'active')
            throw new \UnexpectedValueException("Unexpected load-balancer status $status");

        //Remove nodes
        $liveNode = array_pop($result);
        $nodeIds = array($liveNode['id']);
        $result = $this->connection->removeNodes($lbId, $nodeIds);

        $status = $this->connection->waitForActive($lbId);
        if($status != 'active')
            throw new \UnexpectedValueException("Unexpected load-balancer status $status");
        
    }

    private function testDelete($lbId){

        $this->connection->delete($lbId);
    }

}
