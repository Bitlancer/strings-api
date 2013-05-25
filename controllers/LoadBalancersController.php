<?php

namspace Application;

class LoadBalancersController extends ResourcesController
{

	public function __construct(){

        parent::__construct();

        self::loadLibrary('strings-loadbalancers');
    }

    public function create(){

        $request = $this->getRequestParameters();

        if(!isset($request['device_id']))
            throw new InvalidArgumentException('Your request is missing the strings device id.');

        //Check status of the device and make sure we should proceed w/ creation

        //Get device and provider info
        $loadBalancerProvider = "";
        $providerConnParameters = "";
        $lbParameters = "";

        //Create a connection to the provider
        $providerDriver = null;
        switch($loadBalancerProvider){
            case 'rackspace':
                $providerDriver = new \StringsLoadBalancer\RackspaceLoadBalancerDriver($providerConnParameters);
                break;
            default:
                throw new InvalidArgumentException('Unrecognized provider');
        }

        //Create the load balancer
			
        $loadBalancer = $providerDriver->create($lb['name'],$lb['protocol'],$lb['port'],$lb['algorithm'],$lb['virtualIpType'],
												$lb['nodes'],$lb['providerSpecificParameters']);

        //Save the instance info in the DB

    }

    public function getAlgorithms(){

	}

    public function getProtocols(){

	}

    public function getLoadBalancers(){

	}

    public function delete(){

	}

    public function getNodes($loadBalancerId){

	}

    public function addNode($loadBalancerId,$node){

	}

    public function removeNode($loadBalancerId,$node){

	}

}
