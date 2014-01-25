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

        $providerDriver = $this->getProviderDriver(
            $device['device.implementation_id'],
            $deviceAttrs['implementation.region_id']
        );

        if(!isset($deviceAttrs['implementation.id'])){

            /*
            * When a LB has a shared VIP, it must wait for its peer to be created
            * so it can grab the VIP id from it. So until the peer has hit the
            * active state, throw a 503.
            */
            if(isset($deviceAttrs['implementation.peer_lb'])){

                $peerLBName = $deviceAttrs['implementation.peer_lb'];
                $organizationId = $device['device.organization_id'];
                $peerLB = $this->Device->getByName($organizationId, $peerLBName);
                if(empty($peerLB)){
                    $this->updateStringsStatus($device, 'error');
                    throw new Exception('Could not find peer load-balancer.');
                }
                if($peerLB['device.status'] == 'error') {
                    $this->updateStringsStatus($device, 'error');
                    throw new Exception('Peer LB creation failed.');
                }
                elseif($peerLB['device.status'] == 'active'){
                    $peerLBAttrs = $peerLB['device_attribute'];
                    $virtualIps = json_decode($peerLBAttrs['implementation.virtual_ips'], true);
                    foreach($virtualIps as $ip){
                        if($ip['type'] == strtoupper($deviceAttrs['implementation.virtual_ip_type'])){
                            $this->Device->saveAttribute(
                                $device,
                                'implementation.peer_lb_virtual_ip_id',
                                $ip['id']
                            );
                            $deviceAttrs['implementation.peer_lb_virtual_ip_id'] = $ip['id'];
                            break;
                        }
                    }
                }
                else {
                    return $this->temporaryFailure("Waiting for peer LB build to complete."); 
                }
            }

            //Build the node list
            $formationDevices = $this->Device->getByFormationId($device['device.formation_id']);
            $nodes = array();
            foreach($formationDevices as $d){
                if($d['device_type.name'] == 'instance'){
                    //A device in the building state will not have its ip address
                    //set yet so we must wait
                    if($d['device.status'] == 'building'){
                        return $this->temporaryFailure("Waiting for node to finish building.");
                    }
                    else {
                        $ipAttr = $this->virtualIpTypeToIpAttr(
                            $deviceAttrs['implementation.virtual_ip_type']
                        );
                        $nodes[] = array(
                            'address' => $this->Device->getAttribute(
                                    $d['device.id'],
                                    $ipAttr
                            ),
                            'port' => intval($deviceAttrs['implementation.port']),
                            'condition' => 'ENABLED'
                        );
                    }
                }
            }

            $lb = array(
                'name' => $device['device.name'],
                'protocol' => $deviceAttrs['implementation.protocol'],
                'port' => intval($deviceAttrs['implementation.port']),
                'algorithm' => $deviceAttrs['implementation.algorithm'],
                'nodes' => $nodes
            );

            if(isset($deviceAttrs['implementation.peer_lb_virtual_ip_id'])){
                $lb['virtualIps'] = array(
                    array(
                        'id' => $deviceAttrs['implementation.peer_lb_virtual_ip_id']
                    )
                );
            }
            else {
                $lb['virtualIps'] = array(
                    array(
                        'type' => strtoupper($deviceAttrs['implementation.virtual_ip_type'])
                    )
                );
            }

            if(isset($deviceAttrs['implementation.session_persistence'])){
                $lb['sessionPersistence'] = array(
                    'persistenceType' => $deviceAttrs['implementation.session_persistence']
                );
            }

            try {
                $providerDevice = $providerDriver->create($lb);
            }
            catch(\Exception $e){
                $this->Device->updateStringsStatus($device, 'error');
                throw $e;
            }

            $providerDeviceId = $providerDevice['id'];
            $this->Device->saveAttribute($device,'implementation.id',$providerDeviceId);
            $this->Device->updateStringsStatus($device, 'building');

            $this->temporaryFailure("Waiting for device build to complete");
        }
        else {

            $providerDeviceId = $deviceAttrs['implementation.id'];

            $providerDevice = $providerDriver->get($providerDeviceId);
            $liveDeviceStatus = $providerDevice['status'];

            if($liveDeviceStatus == 'building'){
                $this->temporaryFailure("Waiting for device to spin up");
            }
            elseif($liveDeviceStatus == 'active'){
               
                //Virtual ip
                $this->Device->saveAttribute(
                    $device,
                    'implementation.virtual_ips',
                    json_encode($providerDevice['virtualIps'])
                );

                //Nodes
                $nodes = "";
                if(isset($providerDevice['nodes']))
                    $nodes = $providerDevice['nodes'];
                $this->Device->saveAttribute(
                    $device,
                    'implementation.nodes',
                    json_encode($nodes)
                );

                //Source addresses
                foreach($providerDevice['sourceAddresses'] as $name => $addr){
                    $attrSuffix = false;
                    switch($name) {
                        case 'ipv6Public':
                           $attrSuffix = 'public.6';
                           break;
                        case 'ipv4Public':
                            $attrSuffix = 'public.4';
                            break;
                        case 'ipv4Servicenet':
                            $attrSuffix = 'private.4';
                            break;
                        case 'ipv6Servicenet':
                            $attrSuffix = 'private.6';
                            break;
                        default:
                            break;
                    }

                    if($attrSuffix){
                        $this->Device->saveAttribute(
                            $device,
                            'implementation.address.' . $attrSuffix,
                            $addr
                        );
                    }
                }

                $this->Device->updateStringsStatus($device, 'active');
            }
            else {
                $this->Device->updateStringsStatus($device,$liveDeviceStatus);
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
        }
    }

    private function virtualIpTypeToIpAttr($virtualIpType){

        if($virtualIpType == "public")
            return "implementation.address.public.4";
        else
            return "implementation.address.private.4";
    }

    public function delete($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver(
            $device['device.implementation_id'],
            $deviceAttrs['implementation.region_id']
        );

        try {
            $providerDriver->delete($providerDeviceId);
        }
        catch(\Exception $e){
            $this->Device->updateStringsStatus($device, 'error');
            throw $e;
        }

        $this->Device->delete($deviceId);
    }

    /**
     * Update a load-balancers basic attributes
     */
    public function update($deviceId){
        throw new \Exception('Not implemented');
    }

    /**
     * Manage nodes for a load-balancer
     */
    public function manageNodes($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver(
            $device['device.implementation_id'],
            $deviceAttrs['implementation.region_id']
        );

        $nodeChanges = $this->getPostParameters();

        if(isset($nodeChanges['add'])){

            $nodeAddrs = $nodeChanges['add'];
            $lbPort = $deviceAttrs['implementation.port'];

            $nodes = array();
            foreach($nodeAddrs as $nodeAddr){
                $nodes[] = array(
                    'address' => $nodeAddr,
                    'port' => $lbPort,
                    'condition' => 'ENABLED'
                );
            }

            try {
                $providerDriver->addNodes($providerDeviceId, $nodes, true, 60);
                $providerDevice = $providerDriver->get($providerDeviceId);

                $this->Device->saveAttribute(
                    $device,
                    'implementation.nodes',
                    json_encode($providerDevice['nodes'])
                );
            }
            catch(\Exception $e) {
                $this->Device->updateStringsStatus($device, 'error');
                throw $e;
            }
        }

        if(isset($nodeChanges['remove'])){

            $nodeIds = array();
            $nodeAddrs = $nodeChanges['remove'];
            $nodes = json_decode($deviceAttrs['implementation.nodes'], true);
            foreach($nodes as $node){
                if(in_array($node['address'],$nodeAddrs)){
                    $nodeIds[] = $node['id'];
                }
            }

            try {
                $providerDriver->removeNodes($providerDeviceId, $nodeIds, true, 60);
                $providerDevice = $providerDriver->get($providerDeviceId);
                $newNodes = array();
                if(isset($providerDevice['nodes']))
                    $newNodes = $providerDevice['nodes'];
                $this->Device->saveAttribute(
                    $device,
                    'implementation.nodes',
                    json_encode($newNodes)
                );
            }
            catch(\Exception $e) {
                $this->Device->updateStringsStatus($device, 'error');
                throw $e;
            }
        }

        $this->Device->updateStringsStatus($device, 'active');
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
