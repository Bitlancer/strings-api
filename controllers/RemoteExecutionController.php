<?php


namespace Application;

class RemoteExecutionController extends ResourcesController
{

    protected $uses = array(
        'Script','Device','JumpServer',
        'Config','Application','Formation'
    );

    public function __construct(){

        parent::__construct();
    }

    public function run($scriptId){

        //Get the script
        $script = $this->Script->get($scriptId);
        if(empty($script))
            throw new NotFoundException('Script does not exist.');

        $orgId = $script['script.organization_id'];

        //Get the related model, ex: Application
        $relatedModelName = $this->getRelatedModelName($script);

        //Get the collection of devices associated with this script
        //ex: Script -> Application -> Devices
        $devices = $this->getAssociatedDevices($script);
        if(empty($devices))
            return;

        //Get the cloud files credentials
        $cloudFilesCreds = $this->Config->getCloudFilesCredentials($orgId);

        //Get private key for pulling code from git
        $gitPrivateKey = $this->Config->getGitPrivateKey($orgId);

        //Grab info for an appropriate jump server to use for these devices
        $jumpServerInfo = $this->getJumpServerInfo($devices);

        //Write jump server private key to temp file
        $privateKeyFile = $this->writePrivateKey($jumpServerInfo);

        //Generate a function for executing ssh cmds
        $execSshCmd = $this->generateSshExecFunction($jumpServerInfo, $privateKeyFile);

        $exception = false;
        $tmpDir = false;
        $lastExitCode = 0;
        $output = array();
        try {

            list($lastExitCode, $opOutput, $tmpDir) = $this->createTempRemoteDir($execSshCmd, $script, $relatedModelName);
            $output += $opOutput;
            if($lastExitCode == 0){
                list($lastExitCode, $opOutput) = $this->pullDownCode($execSshCmd,$tmpDir,$script);
                $output += $opOutput;
                if($lastExitCode == 0){
                    list($lastExitCode, $opOutput) = $this->runScript($execSshCmd, $tmpDir,
                                                                    $script, $relatedModelName,
                                                                    $devices);
                    $output += $opOutput;
                }
            }

            $this->set(array(
                'exitCode' => $lastExitCode,
                'output' => $output
            ));
        } 
        catch(Exception $e){
            $exception = $e;
        }
        { //Finally

            //Cleanup
            try {
                $this->remoteCleanup($execSshCmd,$tmpDir);
            }
            catch(Exception $e){}
            unlink($privateKeyFile);

            //Throw exception
            if($exception !== false)
                throw $exception;
       }
    }

    private function writePrivateKey($jumpServerInfo){

        $privateKey = $jumpServerInfo['private_key'];

        $privateKeyFile = tempnam(REMOTE_EXECUTION_PRIVATE_KEY_DIR,'key_');
        file_put_contents($privateKeyFile,$privateKey);
        
        return $privateKeyFile;
    }

    private function generateSshExecFunction($jumpServerInfo,$privateKeyFile){
        
        $jumpServerFqdn = $jumpServerInfo['fqdn'];
        
        return function($remoteCmd) use($jumpServerFqdn,$privateKeyFile){ 

            $user = REMOTE_EXECUTION_USER;
            $host = $jumpServerFqdn;
            $options = "-o StrictHostKeyChecking=no -q";

            $remoteCmd = escapeshellarg($remoteCmd);
            $sshCmd = "ssh $user@$host -i $privateKeyFile $options $remoteCmd 2>&1";
            exec($sshCmd,$output,$status);

            return array($status,$output);
        };
    }

    private function createTempRemoteDir($execSshCmd, $script, $relatedModelName){

        $relatedModelType = strtolower($script['script.model']);

        $remoteDirName = implode(".",array($relatedModelType,$relatedModelName,rand(1000000, 9999999)));

        $cmd = "mkdir ~/$remoteDirName";
        list($status,$output) = $execSshCmd($cmd);
        return array($status, $output, $remoteDirName);
    }

    private function pullDownCode($execSshCmd, $tmpDir, $script){

        switch($script['script.type']){
            case 'git':
                return $this->pullDownGitCode($execSshCmd, $tmpDir, $script);
            default:
                throw new Exception('Unknown script type supplied');
        }
    }

    private function pullDownGitCode($execSshCmd, $tmpDir, $script){

        //Get private key for pulling code from git
        $gitPrivateKey = $this->Config->getGitPrivateKey($script['script.organization_id']);

        $cmd = implode(";", array(
            "cd ~/$tmpDir",
            "echo \"$gitPrivateKey\" > ./git.key",
            "chmod 0600 ./git.key",
            "echo '#!/bin/sh\nssh -i ./git.key -o StrictHostKeyChecking=no $@' > ./git.sh",
            "chmod +x ./git.sh",
            "GIT_SSH=./git.sh git clone {$script['script.url']} script > /dev/null"
        ));

        return $execSshCmd($cmd);
    }

    private function runScript($execSshCmd, $tmpDir, $script, $relatedModelName, $devices){
        
        $paramList = $this->generateParameterList($script, $relatedModelName, $devices);
        $paramStr = implode(' ', $paramList);

        $relScriptPath = "./script/{$script['script.path']}";

        $cmd = implode(";", array(
            "cd ~/$tmpDir",
            "chmod +x $relScriptPath",
            "$relScriptPath $paramStr"
        ));
        
        return $execSshCmd($cmd);
    }

    private function remoteCleanup($execSshCmd, $tmpDir){
        
        $cmd = "rm -rf ~/$tmpDir";
        return $execSshCmd($cmd);
    }

    private function generateParameterList($script, $relatedModelName, $devices){

        $modelParam = "--model " . $script['script.model'];
        $modelNameParam = "--model-name \"$relatedModelName\"";
        $devicesParam = "--server-list \"" . $this->devicesParameterString($devices) . "\"";
        $cloudFilesCreds = $this->Config->getCloudFilesCredentials($script['script.organization_id']);
        $cloudFilesCredsParam = "--cloud-files-credentials \"$cloudFilesCreds\"";
        $userSuppliedParam = $script['script.parameters'];
        
        return array(
            $modelParam,
            $modelNameParam,
            $devicesParam,
            $cloudFilesCredsParam,
            $userSuppliedParam
        );
    }

    private function getRelatedModelName($script){

        $model = $script['script.model'];
        $modelId = $script['script.foreign_key_id'];

        $relatedModel = false;
        $relatedModelName = false;

        switch($model){
            case 'Application':
                $relatedModel = $this->Application->get($modelId);
                $relatedModelName = $relatedModel['application.name'];
                break;
            case 'Formation':
                $relatedModel = $this->Formation->get($modelId);
                $relatedModelName = $relatedModel['formation.name'];
                break;
            default:
                throw new ServerException("Unexpected model $model.");
        }

        return $relatedModelName;
    }

    /**
     * Get the list of the devices this script is associated with
     */
    private function getAssociatedDevices($script){

        $scriptModel = $script['script.model'];
        $modelId = $script['script.foreign_key_id'];

        $devices = array();
        
        switch($scriptModel){
            case 'Application':
                $devices = $this->Device->getByApplicationIdWithProfiles($modelId);
                break;
            case 'Formation':
                $devices = $this->Device->getByFormationIdWithProfiles($modelId);
                break;
            default:
                throw new ServerException("Unexpected model association $scriptModel.");
        }

        return $devices;
    }

    /**
     * Return the appropriate jump server to use for a collection of devices
     *
     * For now, it selects the jump server associated with the region for the
     * first device.
     */
    private function getJumpServerInfo($devices){

        $device = array_pop($devices);
        $implementationId = $device['device.implementation_id'];
        $regionId = $this->Device->getAttribute(
            $device['device.id'],
            'implementation.region_id');

        $jumpServer = $this->JumpServer->getByImplementationAndRegion(
            $implementationId,
            $regionId);

        if(empty($jumpServer))
            throw new ServerException("Could not determine what jump server " .
                                      "to use for this execution."); 

        $jumpServerDeviceId = $jumpServer['jump_server.device_id'];

        //Get jump server internal FQDN
        $jumpServerFQDN = $this->Device->getAttribute(
            $jumpServerDeviceId,
            'dns.internal.fqdn');
            
        if(empty($jumpServerFQDN))
            throw new ServerException("Attribute dns.internal.fqdn has not " .
                                      "been defined for device $jumpServerDeviceId");

        return array(
            'fqdn' => $jumpServerFQDN,
            'private_key' => $jumpServer['jump_server.private_key']
        );
    }

    private function devicesParameterString($devices){

        $devicesParams = array();

        foreach($devices as $device){

            $deviceParams = array();

            $deviceParams[] = $device['device_attribute.val']; //dns.internal.fqdn
            $deviceParams[] = $device['role.name'];
            
            foreach($device['profiles'] as $profile)
                $deviceParams[] = $profile['profile.name'];
            
            $devicesParams[] = implode(',',$deviceParams);
        }

        return implode(";",$devicesParams);
    }
}
