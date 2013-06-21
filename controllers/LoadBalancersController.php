<?php

namespace Application;

class LoadBalancersController extends ResourcesController
{

    protected $uses = array('Device','Implementation');

	public function __construct(){

        parent::__construct();
        self::loadLibrary('strings-loadbalancers');
    }

    public function create($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        if(!isset($deviceAttrs['implementation.id'])){

            $nodes = json_decode($deviceAttrs['implementation.nodes'],true);

            $providerDevice = $providerDriver->create(
                $device['device.name'],
                $deviceAttrs['implementation.protocol'],
                $deviceAttrs['implementation.port'],
                $deviceAttrs['implementation.algorithm'],
                $deviceAttrs['implementation.virtualIpType'],
                $nodes
            );

            $providerDeviceId = $providerDevice['id'];
            $this->Device->saveAttribute($device,'implementation.id',$providerDeviceId);

            $nodes = $providerDevice['nodes'];
            $this->Device->saveAttribute($device,'implementation.nodes',json_encode($nodes));

            $newDeviceStatus = $providerDriver->getStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);

            $this->temporaryFailure("Waiting for device build to complete");
        }
        else {

            $providerDeviceId = $deviceAttrs['implementation.id'];

            $liveDeviceStatus = $providerDriver->getStatus($providerDeviceId);

            if($liveDeviceStatus == 'build'){
                $this->temporaryFailure("Waiting for device to spin up");
            }
            elseif($liveDeviceStatus == 'active'){

                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            }
            else {
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
        }
    }

    public function delete($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $providerDriver->delete($providerDeviceId);
        $this->Device->delete($deviceId);
    }

    public function getAlgorithms($implementationId,$region){
        
         if(empty($implementationId))
            throw new InvalidArgumentException('Implementation id is required');

        if(empty($region))
            throw new InvalidArgumentException('Region is required');

        if(!$this->Implementation->exists($implementationId))
            throw NotFoundException('Implementation does not exist');

        $providerDriver = $this->getProviderDriver($implementationId,$region);

        $algos = $providerDriver->getAlgorithms();

        $this->set(array(
           'algorithms' => $algos
        ));
	}

    public function getProtocols($implementationId,$region){

        if(empty($implementationId))
            throw new InvalidArgumentException('Implementation id is required');

        if(empty($region))
            throw new InvalidArgumentException('Region is required');

        if(!$this->Implementation->exists($implementationId))
            throw NotFoundException('Implementation does not exist');

        $providerDriver = $this->getProviderDriver($implementationId,$region);

        $protocols = $providerDriver->getProtocols();

        $this->set(array(
           'protocols' => $protocols
        ));
	}

    public function updateNodes($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $nodes = $providerDriver->getNodes($providerDeviceId);
        
        $this->Device->saveAttribute($device,'implementation.nodes',json_encode($nodes));
	}

    public function addNode($deviceId,$nodeIp,$nodePort){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(empty($nodeIp))
            throw new InvalidArgumentException('Node ip is required');

        if(empty($nodePort))
            throw new InvalidArgumentException('Node port is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $node = array(
            'ip' => $nodeIp,
            'port' => $nodePort
        );

        $providerDriver->addNode($providerDeviceId,$node,true);

        $this->updateNodes($deviceId);
	}

    public function removeNode($deviceId,$nodeId){

       if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(empty($nodeId))
            throw new InvalidArgumentException('Node id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $providerDriver->removeNode($providerDeviceId,$nodeId,true);

        $this->updateNodes($deviceId);
	}

    protected function getProviderDriver($implementationId,$region){

        $implementation = $this->Implementation->get($implementationId);
        $implementationAttrs = $implementation['implementation_attribute'];

        $infrastructureProvider = strtolower($implementation['provider.name']);

        $providerDriver = null;
        switch($infrastructureProvider){
            case 'rackspace':
                $connParameters = array(
                    'credentials' => array(
                        'username' => $implementationAttrs['username'],
                        'secret' => $implementationAttrs['api_key']
                    ),
                    'region' => $region,
                    'identityApiEndpoint' => $implementationAttrs['identity_api_endpoint']
                );
                $providerDriver = new \StringsLoadbalancer\RackspaceLoadBalancerDriver($connParameters);
                break;
            case 'openstack':
                throw new ServerException('Not implemented');
                break;
            default:
                throw new InvalidArgumentException('Unrecognized provider');
        }

        return $providerDriver;
    }

}
