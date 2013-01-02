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

class lwLoginProcess extends lw_object
{
    public function __construct($params, $config, $request, $repository) 
    {
        $this->config = $config;
        $this->request = $request;
        $this->params = $params;
        $this->repository = $repository;
        
        $this->setBaseDir();
        $this->loadAdapter();
    }
    
    public function setBaseDir()
    {
        if (is_dir($this->config['plugin_path']['lw'])) {
            $this->basedir = $this->config['plugin_path']['lw'] . "lw_login/";
        } 
        elseif (is_dir($this->config['path']['plugins'])) {
            $this->basedir = $this->config['path']['plugins'] . "lw_login/";
        } 
        else {
            throw new Exception('Plugin Verzeichnis -lw_login- exitiert nicht!');
        }
    }    
    
    public function loadAdapter()
    {
        $adapter = $this->config['lw_login']['adapter'];
        if (!$adapter) {
            $adapter = "ContentoryIntranet";
        }
        $file = dirname(__FILE__).'/../adapter/lwLogin'.$adapter.'Adapter.php';
        if (is_file($file)) {
            include_once($file);
            $class = "lwLogin".$adapter."Adapter";
            $this->adapter = new $class($this->config, $this->repository);
        }
        else {
            throw new Exception('The Adapter "'.$adapter."\" doesn't exist");
        }
    }    
    
    public function execute()
    {
        if ($this->request->getAlnum('logcmd') == 'logout') {
            $this->logout();
        }

        if ($this->params['pwlost'] == '1' && ($this->request->getAlnum('logcmd') == 'pwlost' || $this->request->getAlnum('logcmd') == 'resetpw')) {
            include_once(dirname(__FILE__).'/classes/lwPasswordLost.php');
            $pwlost = new lwPasswordLost($this->adapter, $this->params, $this->config, $this->request, $this->repository, $this->basedir);
            return $pwlost->execute($this->request->getAlnum('logcmd'));
        }        

        if (strlen(trim($this->request->getAlnum('lw_login_name'))) > 0 && strlen(trim($this->request->getRaw('lw_login_pass'))) > 0) {
            $this->login();
        } 
        else {
            if ($this->adapter->isLoggedIn()) {
                return $this->_buildLogout();
            } 
            else {
                return $this->_buildLogin();
            }
        }    
    }
    
    public function logout() 
    {
        $this->adapter->logout();
        $url = $this->_getLogoutUrl();
        $this->pageReload($url);
        exit();
    }
    
    public function login() 
    {
        $ok = $this->adapter->login($this->request->getRaw('lw_login_name'), $this->request->getRaw('lw_login_pass'));
        if (!$ok) {
            $this->pageReload(lw_page::getInstance()->getUrl(array("lw_login_error"=>"1")));
        } 
        else {
            $this->pageReload($this->_getTargetUrl());
        }
        exit();
    }    
    
    public function _getTargetUrl() 
    {
        if (strlen($this->params['targeturl']) > 0) {
            return $this->params['targeturl'];
        } 
        elseif (intval($this->params['targetid']) > 0) {
            return lw_page::getInstance($this->params['targetid'])->getUrl();
        } 
        elseif ($this->adapter->existsIntranetTarget()) {
            return $this->adapter->getIntranetTargetUrl();
        } 
        else {
            return lw_page::getInstance()->getUrl();
        }
    }    
    
    public function _getLogoutUrl() 
    {
        if (strlen($this->params['logouturl']) > 0) {
            return $this->params['logouturl'];
        } 
        elseif (intval($this->params['logoutid']) > 0) {
            return lw_page::getInstance($this->params['logoutid'])->getUrl();
        } 
        else {
            return lw_page::getInstance()->getUrl();
        }
    }
    
    public function _buildLogout() 
    {
        if ($this->params['redirectifloggedin'] == "1") {
            $this->pageReload($this->_getTargetUrl());
        }    
        $view = new lw_view($this->basedir."templates/logout.tpl.phtml");
        $view->logouturl = lw_page::getInstance()->getUrl(array("logcmd" => "logout"));
        $view->username = $this->adapter->getUsername();
        $view->lang = $this->params['lang'];
        if ($view->lang != "en") {
            $view->lang = "de";
        }
        return $view->render();
    }
    
    public function _buildLogin() 
    {
        $view = new lw_view($this->basedir."templates/login.tpl.phtml");
        $view->action = lw_page::getInstance()->getUrl();
        $view->error = $this->request->getInt('lw_login_error');
        $view->lang = $this->params['lang'];
        if ($view->lang != "en") {
            $view->lang = "de";
        }
        $view->showPWLost = ($this->params['pwlost'] == '1') ? true : false;
        $view->pwlosturl = lw_page::getInstance()->getUrl(array("logcmd" => "pwlost"));
        return $view->render();        
    }     
}    
