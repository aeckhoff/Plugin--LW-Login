<?php

/* * ************************************************************************
 *  Copyright notice
 *
 *  Copyright 2009-2012 Logic Works GmbH
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

class lw_login extends lw_plugin 
{
    function __construct($pid=false) 
    {
        $this->config = lw_registry::getInstance()->getEntry("config");
        $this->request = lw_registry::getInstance()->getEntry("request");
        $this->repository = lw_registry::getInstance()->getEntry("repository");
        $this->fPost = lw_registry::getInstance()->getEntry("fPost");
        $this->fGet = lw_registry::getInstance()->getEntry("fGet");

        $this->handleSecureUrl();
    }

    public function handleSecureUrl()
    {
        //check;: https forced on intranet pages
        $httpsPort = (isset($this->config['general']['https_port']) && (is_numeric($this->config['general']['https_port']))) ? $this->config['general']['https_port'] : 443;
        if ($this->config['general']['HTTPSallowed'] && (!isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "on" && $_SERVER['SERVER_PORT'] != $httpsPort)) {
            header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    public function setParameter($param) 
    {
        $parts = explode("&", $param);
        foreach ($parts as $part) {
            $sub = explode("=", $part);
            $this->params[$sub[0]] = $sub[1];
        }
    }

    function buildPageOutput() 
    {
        $this->loadLoginProcess();
        return $this->process->execute();
    }
    
    public function loadLoginProcess()
    {
        $file = dirname(__FILE__).'/classes/lwLoginProcess.php';
        if (is_file($file)) {
            include_once($file);
            $this->process = new lwLoginProcess($this->params, $this->config, $this->request, $this->repository);
        }
        else {
            throw new Exception("The lwLoginProcess Class doesn't exist");
        }
    }    
}
