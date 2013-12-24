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

            /*
            * When a LB has a shared VIP, it must wait for its peer to be created
            * so it can grab the VIP id from it. So until the peer has hit the
            * active state, throw a 503. 
            */
            if($deviceAttrs['implementation.virtualIpType'] == 'shared'){

                $peerLBName = $deviceAttrs['implementation.virtualIpPeer'];
                $peerLB = $this->Device->getByName($organizationId,$peerLBName);
                if(empty($peerLB))
                    throw new Exception('Could not find peer load-balancer.');
                if($peerLB['device.state'] == 'error')
                    throw new Exception('Peer LB creation failed.');
                elseif($peerLB['device.state'] == 'active'){
                    $peerLBAttrs = $peerLB['device_attribute'];
                    $virtualIpId = $peerLBAttrs['implementation.virtualIpId'];
                    $this->Device->saveAttribute($device,'implementation.virtualIpId',$virtualIpId);
                    $deviceAttrs['implementation.virtualIpId'] = $virtualIpId;
                }
                else {
                    return $this->temporaryFailure("Waiting for peer LB build to complete."); 
                }
            }

            $nodes = array();
            if(isset($deviceAttrs['implementation.nodes']))
                $nodes = json_decode($deviceAttrs['implementation.nodes'],true);

            $lb = array(
                'name' => $device['device.name'],
                'protocol' => $deviceAttrs['implementation.protocol'],
                'port' => $deviceAttrs['implementation.port'],
                'algorithm' => $deviceAttrs['implementation.algorithm'],
                'virtualIpType' => $deviceAttrs['implementation.virtualIpType'],
                'nodes' => $nodes
            );

            if($deviceAttrs['implementation.virtualIpType'] == 'shared'){
                unset($lb['virtualIpType']);
                $lb['virtaulIpId'] = $deviceAttrs['implementation.virtualIpId'];
            }

            $providerDevice = $providerDriver->create($lb);

            $providerDeviceId = $providerDevice['id'];
            $this->Device->saveAttribute($device,'implementation.id',$providerDeviceId);

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

                throw new Exception('Not implemented');

                //Need to set virtaulIpId and nodes

                $nodes = $providerDevice['nodes'];
                $this->Device->saveAttribute($device,'implementation.nodes',json_encode($nodes));

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

    /**
     * Update a load-balancers basic attributes
     */
    public function update($deviceId){
        throw new \Exception('Not implemented');
    }

    /**
     * Update a load-balancers session persistence setting
     */
    public function updateSessionPersistence($deviceId){
        throw new \Exception('Not implemented');
    }

    public function updateNodes($deviceId){

        throw new \Exception('Not implemented');

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
