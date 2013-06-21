<?php

namespace Application;

class DnsController extends ResourcesController
{

    protected $uses = array('Device','Implementation','Config');

    public function __construct(){

        parent::__construct();
        $this->loadLibrary('strings-dns');
    }

    public function addDeviceARecord($deviceId=null){

        if(empty($deviceId))
            throw new InvalidArgumentException('Your request is missing the strings device id.');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);
        $organizationId = $device['device.organization_id'];
        $implementationId = $device['device.implementation_id'];
        $deviceAttrs = $device['device_attribute'];
        $deviceRegion = $deviceAttrs['implementation.region_id'];

        $dnsRegion = $this->Config->getOption($organizationId,'dns.external.region_id');
        if($dnsRegion === false)
            throw new ClientException('External DNS region has not been configured');

        $providerDriver = $this->getProviderDriver($organizationId,$dnsRegion);

        $domainId = $this->Config->getOption($organizationId,'dns.external.domain_id');
        if($domainId === false)
            throw new ClientException('External DNS domain id has not been configured');

        if(!isset($deviceAttrs['dns.external.fqdn']))
            throw new ClientException('Device external FQDN attribute (dns.external.fqdn) is not set');
        $deviceFQDN = strtolower($deviceAttrs['dns.external.fqdn']);

        $ipAddress = $deviceAttrs['implementation.address.public.4'];

        $ttl = $this->Config->getOption($organizationId,'dns.external.record_ttl');
        if($ttl === false)
            throw new ClientException('External DNS record ttl has not been configured');

        $record = new \StringsDns\DnsRecord(null,'A',$deviceFQDN,$ipAddress,$ttl,false);
        $record = $providerDriver->addDomainRecord($domainId,$record,true);

        $this->Device->saveAttribute($device,'dns.external.arecord_id',$record->id);
    }

    public function removeDeviceARecord($deviceId=null){

        if(empty($deviceId))
            throw new InvalidArgumentException('Your request is missing the strings device id.');

        if(!$this->Device->exists($deviceId))
            throw new NotFoundException('Device does not exist');

        $device = $this->Device->get($deviceId);

        $organizationId = $device['device.organization_id'];
        $deviceAttrs = $device['device_attribute'];

        if(!isset($deviceAttrs['dns.external.arecord_id']))
            throw new ServerException('Device DNS attribute (dns.external.arecord_id) does not exist');

        $deviceARecordId = $deviceAttrs['dns.external.arecord_id'];
        
        $domainId = $this->Config->getOption($organizationId,'dns.external.domain_id');
        if($domainId === false)
            throw new ClientException('External DNS region has not been configured');

        $dnsRegion = $this->Config->getOption($organizationId,'dns.external.region_id');
        if($dnsRegion === false)
            throw new ClientException('External DNS region has not been configured');

        $providerDriver = $this->getProviderDriver($organizationId,$dnsRegion);

        $providerDriver->removeDomainRecord($domainId,$deviceARecordId);
    }

    protected function getProviderDriver($organizationId,$region){

        $implementation = $this->getOrganizationDnsImplementation($organizationId);
        if($implementation === false)
            throw new ServerException('A DNS provider has not been configured');

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
                $providerDriver = new \StringsDns\RackspaceDnsDriver($connParameters);
                break;
            case 'openstack':
                throw new ServerException('Not implemented');
                break;
            default:
                throw new InvalidArgumentException('Unrecognized provider');
        }

        return $providerDriver;
    }

    protected function getOrganizationDnsImplementation($organizationId){

        $implementation_id = $this->Config->getOption($organizationId,'dns.external.implementation_id');
        if($implementation_id === false)
            throw new ServerException('A DNS provider has not been configured');

        $implementation = $this->Implementation->get($implementation_id);
        if($implementation === false)
            throw new ServerException('A DNS provider has not been configured');

        return $implementation;
    }
}
