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
        $reg = lw_registry::getInstance();
        $this->config = $reg->getEntry("config");
        $this->request = $reg->getEntry("request");
        $this->repository = $reg->getEntry("repository");
        $this->in_auth = lw_in_auth::getInstance();
        $this->fPost = $reg->getEntry("fPost");
        $this->fGet = $reg->getEntry("fGet");

        if (is_dir($this->config['plugin_path']['lw'])) {
            $this->basedir = $this->config['plugin_path']['lw'] . "lw_login/";
        } 
        elseif (is_dir($this->config['path']['plugins'])) {
            $this->basedir = $this->config['path']['plugins'] . "lw_login/";
        } 
        else {
            throw new Exception('Plugin Verzeichnis -lw_login- exitiert nicht!');
        }

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
        if ($this->request->getAlnum('logcmd') == 'logout') {
            $this->_logout();
        }

        if ($this->request->getAlnum('logcmd') == 'pwlost' && $this->params['pwlost'] == '1') {
            include_once(dirname(__FILE__).'/lw_password_lost.class.php');
            $pwlost = new lw_password_lost($this->params);
            return $pwlost->handlePasswordLost();
        }
        if ($this->request->getAlnum('logcmd') == 'resetpw' && $this->params['pwlost'] == '1') {
            include_once(dirname(__FILE__).'/lw_password_lost.class.php');
            $pwlost = new lw_password_lost($this->params);
            return $pwlost->handleResetPassword();
        }

        if (strlen(trim($this->request->getAlnum('lw_login_name'))) > 0 && strlen(trim($this->request->getRaw('lw_login_pass'))) > 0) {
            $this->_login();
        } 
        else {
            if ($this->in_auth->isLoggedIn()) {
                return $this->_buildLogout();
            } 
            else {
                return $this->_buildLogin();
            }
        }
    }

    private function _logout() 
    {
        $this->in_auth->logout();
        $url = $this->_getLogoutUrl();
        $this->pageReload($url);
        exit();
    }
    
    private function _login() 
    {
        $ok = $this->in_auth->login($this->request->getRaw('lw_login_name'), $this->request->getRaw('lw_login_pass'));
        if (!$ok) {
            $this->pageReload("index.php?index=" . $this->request->getIndex() . "&lw_login_error=1");
        } 
        else {
            $url = $this->_getTargetUrl();
            $this->pageReload($url);
        }
        exit();
    }
    
    private function _getLogoutUrl() 
    {
        if (strlen($this->params['logouturl']) > 0) {
            $url = $this->params['logouturl'];
        } 
        elseif (intval($this->params['logoutid']) > 0) {
            $url = $this->config['url']['client'] . "index.php?index=" . $this->params['logoutid'];
        } 
        elseif (strlen($this->params['logoutpagename']) > 0) {
            $url = $this->config['url']['client'] . $this->params['logoutpagename'];
        } 
        else {
            $url = $this->config['url']['client'] . "index.php?index=" . $this->request->getIndex();
        }
        return $url;
    }
    
    private function _getTargetUrl() 
    {
        if (strlen($this->params['targeturl']) > 0) {
            return $this->params['targeturl'];
        } 
        elseif (intval($this->params['targetid']) > 0) {
            return lw_page::getInstance($this->params['targetid'])->getUrl();
        } 
        elseif (strlen($this->params['targetpagename']) > 0) {
            return $this->config['url']['client'] . $this->params['targetpagename'];
        } 
        elseif ($this->in_auth->getUserdata("intranet_id") > 0) {
            $data = $this->repository->getRepository("intranetadmin")->loadData($this->in_auth->getUserdata("intranet_id"));
            $parameter = json_decode($data['parameter'], true);
            return lw_page::getInstance($parameter['targetpage'])->getUrl();
        } 
        else {
            return lw_page::getInstance($this->request->getIndex())->getUrl();
        }
    }

    private function _buildLogout() 
    {
        $view = new lw_view($this->basedir . "templates/logout.tpl.phtml");
        $view->logouturl = lw_page::getInstance()->getUrl(array("logcmd" => "logout"));
        $view->username = $this->in_auth->getUserdata("name");
        $view->lang = $this->params['lang'];
        if ($view->lang != "en")
            $view->lang = "de";
        return $view->render();
    }
    
    private function _buildLogin() 
    {
        $view = new lw_view($this->basedir . "templates/login.tpl.phtml");
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
