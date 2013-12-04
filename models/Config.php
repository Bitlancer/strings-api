<?php

namespace Application;

class Config extends ResourceModel
{
    public function getOption($organizationId,$var){

        if(empty($organizationId) || empty($var))
            throw new InvalidArgumentException('One or more required arguments was empty');

        $query = "
            SELECT val
            FROM config
            WHERE organization_id = :organization_id AND
                var = :var
        ";

        $queryParameters = array(
            ':organization_id' => $organizationId,
            ':var' => $var
        );

        $result = $this->fetch($query,$queryParameters);

        if($result === false)
            return false;

        return $result['config.val'];
    }

    public function getCloudFilesCredentials($organizationId){

        return $this->getOption($organizationId,'deploy.cloud_files.credentials');
    }

    public function getGitPrivateKey($organizationId){

        return $this->getOption($organizationId,'deploy.git.private_key');
    }
}
