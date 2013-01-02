<?php

/* * ************************************************************************
 *  Copyright notice
 *
 *  Copyright 2012 Logic Works GmbH
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *  
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *  
 * ************************************************************************* */

class lwLoginContentoryIntranetAdapter extends lw_object
{
    function __construct($config, $repository) 
    {
        $this->config = $config;
        $this->repository = $repository;
        $this->in_auth = lw_in_auth::getInstance();
    }
    
    function logout()
    {
        return $this->in_auth->logout();
    }
    
    function login($name, $password)
    {
        return $this->in_auth->login($name, $password);    
    }
    
    function existsIntranetTarget()
    {
        if($this->in_auth->getUserdata("intranet_id") > 0)
        {
            return true;
        }
        return false;
    }
    
    function getIntranetTargetUrl()
    {
        $data = $this->repository->getRepository("intranetadmin")->loadData($this->in_auth->getUserdata("intranet_id"));
        $parameter = json_decode($data['parameter'], true);
        return lw_page::getInstance($parameter['targetpage'])->getUrl();
    }
    
    function isLoggedIn()
    {
        return $this->in_auth->isLoggedIn();
    }
    
    function getUsername()
    {
        return $this->in_auth->getUserdata("name");
    }
    
    function getPasswordHash($email)
    {
        return $this->in_auth->getPasswordHash($email);
    }
    
    function checkPasswordHash($code, $uid)
    {
        return $this->in_auth->checkPasswordHash($code, $uid);
    }
    
    function resetPassword($code, $uid, $pass1)
    {
        return $this->in_auth->resetPassword($code, $uid, $pass1);
    }
}    
