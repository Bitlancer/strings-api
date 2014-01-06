<?php

namespace Application;

class InstancesController extends ResourcesController
{

    protected $uses = array('Device','Implementation','Config');

	public function __construct(){

		parent::__construct();
		self::loadLibrary('strings-infrastructure');
	}

	public function create($deviceId){

		if(empty($deviceId))
			throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId); 
        $deviceAttrs = $device['device_attribute'];

        if(isset($deviceAttrs['implementation.status']) && $deviceAttrs['implementation.status'] == 'active')
            throw new ClientException('This device is already running');

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        //Check the device's status to determine what we should do
        if(!isset($deviceAttrs['implementation.id'])){

            $providerDevice = array();
            try {
                $providerDevice = $providerDriver->createServer(
                    $deviceAttrs['dns.external.fqdn'],
                    $deviceAttrs['implementation.flavor_id'],
                    $deviceAttrs['implementation.image_id']);
            }
            catch(\Exception $e){
                $this->Device->updateImplementationStatus($device, 'error');
                throw $e;
            }

            $providerDeviceId = $providerDevice['id'];
            $this->Device->saveAttribute($device,'implementation.id',$providerDeviceId);

            $newDeviceStatus = "build";
            try {
                $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            }
            catch(\Exception $e){
                //For now, let's consider this to be a temporary failure.
                //We should be checking for different exceptions
            }

            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
            $this->temporaryFailure("Waiting for device build to complete");
        }
        else {

            $providerDeviceId = $deviceAttrs['implementation.id'];

            $liveDeviceStatus = "build";
            try {
                $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            }
            catch(\Exception $e){
                //For now let's consider this to be a temporary failure.
                //We should be checking for different exceptions
            }

            if($liveDeviceStatus == 'build'){
                $this->temporaryFailure("Waiting for device to spin up");
            }
            elseif($liveDeviceStatus == 'active'){

                try {

                    $ips = $providerDriver->getServerIPs($providerDeviceId);
                    $this->Device->saveIPs($device,$ips);

                    $this->addDeviceARecord($device['device.id']);

                    $this->maybeScheduleImageBackups($device['device.id']);

                    $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                    $this->Device->updateStringsStatus($device,'active');
                    $this->Device->setCanSyncToLdapFlag($device,1);
                }
                catch(\Exception $e){
                    $this->Device->updateImplementationStatus($device,'error');
                    throw $e;
                }
            }
            else {
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                $this->Device->updateStringsStatus($device,'error');
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
        }
	}

	public function resize($deviceId,$flavorId){

       if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        $getParams = $this->getGetParameters();
        if(!isset($getParams['state'])){

            if($liveDeviceStatus == 'active'){
                $providerDriver->resizeServer($providerDeviceId,$flavorId);

                $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
                $this->Device->updateImplementationStatus($device,$newDeviceStatus);
                $this->Device->updateStringsStatus($device,'resize');

                $this->setHeaderValue('x-bitlancer-url',REQUEST_URL . "?state=waitingForResize");
                
                $this->temporaryFailure("Waiting for device resize to complete");
            }
            else {
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                $this->Device->updateStringsStatus($device,'error');
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
        }
        else {
            if($liveDeviceStatus == 'active' || $liveDeviceStatus == 'verify_resize'){
                $this->Device->saveAttribute($device,'implementation.flavor_id',$flavorId);
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                $this->Device->updateStringsStatus($device,'active');
            }
            elseif($liveDeviceStatus == 'resize'){
                $this->temporaryFailure("Waiting for device resize to complete");
            }
            else {
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                $this->Device->updateStringsStatus($device,'error');
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
        }
	}

    public function confirmResize($deviceId,$confirm){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $confirm = ($confirm == 'false' || $confirm == '0' ? false : true);

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'verify_resize'){
            if($confirm)
                $providerDriver->confirmResizeServer($providerDeviceId,true);
            else {
                $providerDriver->revertResizeServer($providerDeviceId);
                $flavorId = $providerDriver->getServerFlavor($providerDeviceId);
                $this->Device->saveAttribute($device,'implementation.flavor_id',$flavorId);
            }

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
        }
    }

	public function rebuild($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'active'){

            $providerDriver->rebuildServer($providerDeviceId,false,false);

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
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

        $liveDeviceStatus = '';
        try {
            $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
        }
        catch(\OpenCloud\Common\Exceptions\InstanceNotFound $e){
            $this->Device->delete($deviceId);
            return;
        }

        $getParams = $this->getGetParameters();
        if(!isset($getParams['state'])){
            $this->removeDeviceARecord($deviceId);
            $providerDriver->deleteServer($deviceAttrs['implementation.id']);
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            $this->setHeaderValue('x-bitlancer-url',REQUEST_URL . "?state=waitingForDelete");
            $this->temporaryFailure("Waiting for device delete to complete");
        }
        else {
            if($liveDeviceStatus == 'deleted'){
                $this->Device->delete($deviceId);     
            }
            elseif($liveDeviceStatus == 'error' || $liveDeviceStatus == 'unknown'){
                $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
                $this->Device->updateStringsStatus($device,'error');
                throw new UnexpectedProviderStatusException($liveDeviceStatus);
            }
            else {
                $this->temporaryFailure("Waiting for device delete to complete");
            }
        }
	}

	public function reboot($deviceId){

         if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $liveDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);

        if($liveDeviceStatus == 'active'){

            $providerDriver->rebootServer($providerDeviceId);

            $newDeviceStatus = $providerDriver->getServerStatus($providerDeviceId);
            $this->Device->updateImplementationStatus($device,$newDeviceStatus);
        }
        else {
            $this->Device->updateImplementationStatus($device,$liveDeviceStatus);
            throw new UnexpectedProviderStatusException($liveDeviceStatus);
        }
	}

	public function getStatus($deviceId){

        if(empty($deviceId))
            throw new InvalidArgumentException('Device id is required');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');
    
        $device = $this->Device->get($deviceId);
        $deviceAttrs = $device['device_attribute'];

        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver($device['device.implementation_id'],$deviceAttrs['implementation.region_id']);

        $providerStatus = $providerDriver->getServerStatus($providerDeviceId);

        $this->Device->updateImplementationStatus($device,$providerStatus);

       $this->set(array(
            'status' => $providerStatus
        )); 
	}

    public function updateServerStatuses($implementationId,$region){
        
        if(empty($implementationId))
            throw new InvalidArgumentException('Implementation id is required');

        if(empty($region))
            throw new InvalidArgumentException('Region is required');

        $providerDriver = $this->getProviderDriver($implementationId,$region);

        $servers = $providerDriver->getServers();

        //Try to minimize number of DB queries ...
        //Iterate through the list of servers and group them
        //by their statuses. Requires a single query per status vs 
        //a query for each device.
        $serversGroupedByStatus = array();

        foreach($servers as $server)
            $serversGroupedByStatus[$server['status']][] = $server['id'];

        foreach($serversGroupedByStatus as $status => $serverIds)
            $this->Device->setStatusByProviderId($status,$serverIds);
    }

    private function addDeviceARecord($deviceId){

        $this->loadController('Dns');
        $dnsController = new DnsController();
        $dnsController->addDeviceARecord($deviceId);
    }

    private function removeDeviceARecord($deviceId){

        $this->loadController('Dns');
        $dnsController = new DnsController();

        try{
            $dnsController->removeDeviceARecord($deviceId);
        }
        catch(\Exception $e){}
    }

    private function maybeScheduleImageBackups($deviceId){

        $device = $this->Device->get($deviceId);
        $organizationId = $device['device.organization_id'];
        $deviceAttrs = $device['device_attribute'];
        $providerDeviceId = $deviceAttrs['implementation.id'];

        $providerDriver = $this->getProviderDriver(
            $device['device.implementation_id'],
            $deviceAttrs['implementation.region_id']); 

        $retention = $this->Config->getOption(
            $organizationId,
            'implementation.image_schedule.retention'
        );

        if(empty($retention))
            return;

        $providerDriver->createImageSchedule($providerDeviceId,$retention);
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
                $providerDriver = new \StringsInfrastructure\RackspaceInfrastructureDriver($connParameters);
                break;
            case 'openstack':
                throw new ServerException('Not implemented');
                $connParameters = array();
                $providerDriver = new \StringsInfrastructure\OpenStackInfrastructureDriver($connParameters);
                break;
            default:
                throw new InvalidArgumentException('Unrecognized provider');
        }

        return $providerDriver;
    }
}
