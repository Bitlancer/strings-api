<?php

namespace StringsLoadBalancer;

require_once('LoadBalancerDriver.php');
require_once('lib/SimpleRackspaceLib.php');

use \SimpleRackspaceLib\SimpleRackspaceClient;

class RackspaceLoadBalancerDriver extends LoadBalancerDriver
{
	protected function parseConnectionParameters($connParams){
		
		$validConnectionDataStruct = array(
			'region' => '',
            'identityApiEndpoint' => '',
            'credentials' => array(
            	'username' => '',
               	'secret' => ''
            )
		);
		
		if(!self::validDataStructure($validConnectionDataStruct,$connParams))
			throw new \InvalidArgumentException('One or more required provider connection parameters is missing or is invalid.');
		
		$this->region = $connParams['region'];
		$this->identityAPIEndpoint = $connParams['identityApiEndpoint'];
		$this->connectionCredentials = array(
			'username' => $connParams['credentials']['username'],
			'apiKey' => $connParams['credentials']['secret']
		);
	}
	
	protected function getProviderConnection(){

        $connection = new SimpleRackspaceClient(
            $this->connectionCredentials['username'],
            $this->connectionCredentials['apiKey'],
            $this->identityAPIEndpoint,
            'cloudLoadBalancers',
            $this->region
        );
	
		return $connection;
	}

    public function get($loadBalancerId) {
        
        $url = "/loadbalancers/$loadBalancerId";
        list($resp, $status) = $this->connection->request($url);
        if($status != 200)
            throw $this->statusToException($status, $resp);

        $lb = $resp['loadBalancer'];

        $lb['status'] = $this->toGenericStatus($lb['status']);

        return $lb;
    }

    public function create($attrs) {

        $requiredAttrs = array(
            'name',
            'protocol',
            'port',
            'virtualIps'
        );

        foreach($requiredAttrs as $attrName){
            if(!isset($attrs[$attrName])){
                throw new \InvalidArgumentException("$attrName is required."); 
            }
        }

        $data = array(
            'loadBalancer' => $attrs
        );

        list($resp, $status) = $this->connection->request('/loadbalancers',
                                                            'POST',
                                                            $data);

        if($status != 202)
            throw $this->statusToException($status, $resp);

        $lb = $resp['loadBalancer'];

        $lb['status'] = $this->toGenericStatus($lb['status']);

        return $lb;
    }

    public function update($loadBalancerId, $attrs) {

        $newLb = array();

        $updatableFields = array(
            'name',
            'protocol',
            'halfClosed',
            'algorithm',
            'port',
            'timeout',
            'httpsRedirect',
            'sessionPersistence'
        );

        if(empty($attrs))
            throw new \InvalidArgumentException("At least one attribute must be supplied.");

        foreach($attrs as $attrName => $attrVal){
            if(!in_array($attrName, $updatableFields)){
                throw new \InvalidArgumentException("$attrName is not updatable via this method.");
            }
        }

        /*
         * Changing the protocol/port will disable session persistence
         * so we need to set session persistence after everything else.
         */
        $setSessionPersistence = false;
        $sessionPersistenceOption = false;
        if(isset($attrs['sessionPersistence'])){
            $setSessionPersistence = true;
            $sessionPersistenceOption = $attrs['sessionPersistence'];
            unset($attrs['sessionPersistence']);
        }

        //Update standard attrs
        if(!empty($attrs)){

            $data = array(
                'loadBalancer' => $attrs
            );

            $url = "/loadbalancers/$loadBalancerId";

            list($resp, $status) = $this->connection->request($url, 'PUT', $data);

            if($status != 202)
                throw $this->statusToException($status, $resp);

            $newLb = $resp['loadBalancer'];
        }

        if($setSessionPersistence){

            $this->waitForActive($loadBalancerId);

            $data = array(
                'sessionPersistence' => array(
                    'persistenceType' => $sessionPersistenceOption
                )
            );
            $url = "/loadbalancers/$loadBalancerId/sessionpersistence";
            list($resp, $status) = $this->connection->request($url, 'PUT', $data);
            if($status != 200 && $status != 202)
                throw $this->statusToException($status, $resp);

            $newLb = $resp['loadBalancer'];
        }

        $newLb['status'] = $this->toGenericStatus($newLb['status']);

        return $newLb;
    }

    public function delete($loadBalancerId) {


        $url = "/loadbalancers/$loadBalancerId"; 
        list($resp, $status) = $this->connection->request($url, 'DELETE');

        if($status != 202)
            throw $this->statusToException($status, $resp);
    }

    public function getNodes($loadBalancerId) {
        
        $url = "/loadbalancers/$loadBalancerId/nodes";
        list($resp, $status) = $this->connection->request($url);
        if($status != 200 && $status != 202)
            throw $this->statusToException($status, $resp);

        return $resp['nodes'];
    }

    public function addNodes($loadBalancerId, $nodes) {

        $url = "/loadbalancers/$loadBalancerId/nodes";

        $data = array('nodes' => $nodes);

        list($resp, $status) = $this->connection->request($url, 'POST', $data);

        if($status != 200 && $status != 202)
            throw $this->statusToException($status, $resp);

        return $resp['nodes'];
    }

    public function removeNodes($loadBalancerId, $nodes) {
        
        $url = "/loadbalancers/$loadBalancerId/nodes?";

        $idParamValues = array();
        foreach($nodes as $nodeId)
            $idParamValues[] = "id=$nodeId";
        $url .= implode("&", $idParamValues);

        list($resp, $status) = $this->connection->request($url, 'DELETE');
        
        if($status != 200 && $status != 202)
            throw $this->statusToException($status, $resp);

        return $resp['nodes'];
    }

    protected function statusToException($status, $resp){

        $resp = json_encode($resp);

        switch($status){
            case 400:
                return new \InvalidArgumentException($resp);
            case 401:
                return new \RuntimeException('Provider authentication failure');
            case 403:
                return new \RuntimeException('Authorization failure');
            case 404:
                return new \RuntimeException('Resource not found');
            case 413:
                return new \RuntimeException('Limit threshold exceeded');
            case 500:
                return new \RuntimeException("Unexpected error, $resp");
            case 503:
                return new \RuntimeException("Provider service temporarily unavailable. $resp");
            default:
                return new \Exception("Unexpected status code $status");
        }
    }

    protected function toGenericStatus($status){
        return strtolower($status);
    }

    public function waitForActive($loadBalancerId, $timeout=60, $pollInterval=10){

        $status = false;
        $startTime = time();

        while(true){
            $result = $this->get($loadBalancerId);
            $status = $result['status'];

            if(!in_array($status,array('build','pending_update','pending_delete'))){
                break; 
            }
            else
                sleep($pollInterval);

            if(time() - $startTime > $timeout){
                throw new OperationTimeoutException();
            }
        }

        return $status;
    }
}
