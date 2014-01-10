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
        //$this->testDelete($lbId);
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
            )
        ));

        $lbId = $result['id'];

        $status = $this->waitForActive($lbId);
        if($status != 'ACTIVE')
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

        $status = $this->waitForActive($lbId);
        if($status != 'ACTIVE')
            throw new \UnexpectedValueException("Unexpected load-balancer status $status");

        $result = $this->connection->get($lbId);
        $actualAttrs = array(
            'name' => $results['name'],
            'algorithm' => $results['algorithm'],
            'protocol' => $results['protocol'],
            'port' => $results['port']
        );
        $expectedAttrs = $newAttrs;
        $diff = array_diff($actualAttrs, $expectedAttrs);
        if(!empty($diff)){
            throw new \Exception('Load-balancer Attributes do not match.');
        }
    }

    private function testNodes($lbId){

        //Add nodes
        $result = $this->connection->addNodes($lbId, array(
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

        $status = $this->waitForActive($lbId);
        if(!$status != 'ACTIVE')
            throw new \UnexpectedValueException("Unexpected load-balancer status $status");

        //Get nodes
        $result = $this->connection->getNodes($lbId);
        print_r($result);

    }

    private function testDelete($lbId){

        $this->connection->delete($lbId);
    }

    private function waitForActive($loadBalancerId, $pollInterval=10){

        $status = false;

        while(true){
            $result = $this->connection->get($loadBalancerId);
            $status = $result['status'];

            if($status == 'ACTIVE')
                break;
            if($status == 'ERROR')
                break;
            else
                sleep($pollInterval); 
        }

        return $status;
    }
}
