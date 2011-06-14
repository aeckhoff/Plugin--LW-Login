<?php

/* * ************************************************************************
 *  Copyright notice
 *
 *  Copyright 2009-2010 Logic Works GmbH
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

class lw_login extends lw_plugin {

    function __construct($pid=false) {
        $reg = lw_registry::getInstance();
        $this->config = $reg->getEntry("config");
        $this->request = $reg->getEntry("request");
        $this->repository = $reg->getEntry("repository");
        $this->in_auth = lw_in_auth::getInstance();
        $this->fPost = $reg->getEntry("fPost");
        $this->fGet = $reg->getEntry("fGet");

        if (is_dir($this->config['plugin_path']['lw'])) {
            $this->basedir = $this->config['plugin_path']['lw'] . "lw_login/";
        } elseif (is_dir($this->config['path']['plugins'])) {
            $this->basedir = $this->config['path']['plugins'] . "lw_login/";
        } else {
            throw new Exception('Plugin Verzeichnis -lw_login- exitiert nicht!');
        }

        //check;: https forced on intranet pages
        $httpsPort = (isset($this->config['general']['https_port']) && (is_numeric($this->config['general']['https_port']))) ? $this->config['general']['https_port'] : 443;
        if ($this->config['general']['HTTPSallowed'] && (!isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "on" && $_SERVER['SERVER_PORT'] != $httpsPort)) {
            header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    public function setParameter($param) {
        $parts = explode("&", $param);
        foreach ($parts as $part) {
            $sub = explode("=", $part);
            $this->params[$sub[0]] = $sub[1];
        }
    }

    function buildPageOutput() {
        if ($this->request->getAlnum('logcmd') == 'logout') {
            $this->_logout();
        }

        if ($this->request->getAlnum('logcmd') == 'pwlost' && $this->params['pwlost'] == '1') {
            return $this->handlePasswordLost();
        }
        if ($this->request->getAlnum('logcmd') == 'resetpw' && $this->params['pwlost'] == '1') {
            return $this->handleResetPassword();
        }

        if (strlen(trim($this->request->getAlnum('lw_login_name'))) > 0 && strlen(trim($this->request->getRaw('lw_login_pass'))) > 0) {
            $this->_login();
        } else {
            if ($this->in_auth->isLoggedIn()) {
                return $this->_buildLogout();
            } else {
                return $this->_buildLogin();
            }
        }
    }

    private function _logout() {
        $this->in_auth->logout();
        $url = $this->_getLogoutUrl();
        $this->pageReload($url);
        exit();
    }
    
    private function _login() {
        $ok = $this->in_auth->login($this->request->getAlnum('lw_login_name'), $this->request->getRaw('lw_login_pass'));
        if (!$ok) {
            $this->pageReload("index.php?index=" . $this->request->getIndex() . "&lw_login_error=1");
        } else {
            $url = $this->_getTargetUrl();
            $this->pageReload($url);
        }
        exit();
    }
    
    private function _getLogoutUrl() {
        if (strlen($this->params['logouturl']) > 0) {
            $url = $this->params['logouturl'];
        } elseif (intval($this->params['logoutid']) > 0) {
            $url = $this->config['url']['client'] . "index.php?index=" . $this->params['logoutid'];
        } elseif (strlen($this->params['logoutpagename']) > 0) {
            $url = $this->config['url']['client'] . $this->params['logoutpagename'];
        } else {
            $url = $this->config['url']['client'] . "index.php?index=" . $this->request->getIndex();
        }
        return $url;
    }
    
    private function _getTargetUrl() {
        if (strlen($this->params['targeturl']) > 0) {
            return $this->params['targeturl'];
        } elseif (intval($this->params['targetid']) > 0) {
            return lw_page::getInstance($this->params['targetid'])->getUrl();
        } elseif (strlen($this->params['targetpagename']) > 0) {
            return $this->config['url']['client'] . $this->params['targetpagename'];
        } elseif ($this->in_auth->getUserdata("intranet_id") > 0) {
            $data = $this->repository->getRepository("intranetadmin")->loadData($this->in_auth->getUserdata("intranet_id"));
            $parameter = json_decode($data['parameter'], true);
            return lw_page::getInstance($parameter['targetpage'])->getUrl();
        } else {
            return lw_page::getInstance($this->request->getIndex())->getUrl();
        }
    }
    
    private function _buildLogout() {
        $view = new lw_view($this->basedir . "templates/logout.tpl.phtml");
        $view->logouturl = lw_url::get(array("logcmd" => "logout"));
        $view->username = $this->in_auth->getUserdata("name");
        $view->lang = $this->params['lang'];
        if ($view->lang != "en")
            $view->lang = "de";
        return $view->render();
    }
    
    private function _buildLogin() {
        $view = new lw_view($this->basedir . "templates/login.tpl.phtml");
        $view->action = lw_url::get();
        $view->error = $this->request->getInt('lw_login_error');
        $view->lang = $this->params['lang'];
        if ($view->lang != "en")
            $view->lang = "de";
        $view->showPWLost = ($this->params['pwlost'] == '1') ? true : false;
        $view->pwlosturl = $this->buildUrl(array("logcmd" => "pwlost"));
        return $view->render();        
    }
    
    // Password Lost Functionality

    private function sendHashMail($hashData) {
        $hash = $hashData[0];
        $uid = $hashData[1];

        $hashurl = lw_url::get(array('logcmd' => 'resetpw', 'code' => $hash, 'uid' => $uid));

        $view = new lw_view($this->basedir . "templates/email_mail.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != "en")
            $view->lang = "de";

        $view->hashurl = $hashurl;

        if ($this->params['lang'] == "en") {
            $subject = "Restore your password";
        } else {
            $subject = utf8_decode("Passwort zurücksetzen");
        }

        //mail($email, $subject, $msg, 'FROM:passwordlost');

        die(utf8_decode($view->render()));
    }

    private function handlePasswordLost() {
        $view = new lw_view($this->basedir . "templates/email_form.tpl.phtml");
        $view->noemailerror = false;
        $view->error = false;
        $view->showMessage = ($this->params[showmessage] == 1) ? true : false;
        $view->response = false;
        if ($this->fPost->getRaw('email') == 1 && $_SESSION['lw_password_lost_email'] == 1) {
            // Email was send
            unset($_SESSION['lw_password_lost_email']);
            $view->response = true;
            if ($this->fPost->testEmail('lw_login_email')) {
                $hashData = $this->in_auth->getPasswordHash($this->fPost->getRaw('lw_login_email'));
                if (!$hashData) {

                    $view->error = true;
                } else {
                    $this->sendHashMail($hashData);
                    $view->error = false;
                }
            } else {
                $view->noemailerror = true;
            }
        } else {
            $_SESSION['lw_password_lost_email'] = 1;
        }

        $view->action = $this->buildUrl(array("logcmd" => "pwlost"));
        $view->lang = $this->params['lang'];
        $view->action = $this->buildUrl(array("logcmd" => "pwlost"));
        $view->backurl = $this->buildUrl(false, "logcmd");
        if ($this->params['lang'] != "en")
            $view->lang = "de";
        return $view->render();
    }

    private function handleResetPassword() {
        $code = $this->fGet->getAlnum('code');
        $uid = $this->fGet->getInt('uid');
        if ((strlen($code) < 10) || ($uid < 1))
            return $this->buildNotFoundView();
        if (!$this->in_auth->checkPasswordHash($code, $uid))
            return $this->buildNotFoundView();

        if ($this->fPost->getInt('password') == 1) {
            $ok = $this->resetPassword($code, $uid);

            if ($ok)
                return $this->buildSuccessView();
            return $this->buildPasswordView(true);
        }

        return $this->buildPasswordView();
    }

    private function resetPassword() {
        $pass1 = $this->fPost->getRaw('lw_login_pass_1');
        $pass2 = $this->fPost->getRaw('lw_login_pass_2');

        $code = $this->fGet->getAlnum('code');
        $uid = $this->fGet->getAlnum('uid');

        $pass1 = trim($pass1);
        $pass2 = trim($pass2);

        if ($pass1 != $pass2)
            return false;

        if (strlen($pass1) < 5)
            return false;

        $ok = $this->in_auth->resetPassword($code, $uid, $pass1);
        return $ok;
    }

    private function buildPasswordView($error = false) {

        $view = new lw_view($this->basedir . "templates/password.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != 'en')
            $view->lang = "de";

        $code = $this->fGet->getAlnum('code');
        $uid = $this->fGet->getAlnum('uid');

        $view->notfound = false;
        $view->found = true;
        $view->success = false;
        $view->showpassworddialog = true;
        $view->error = $error;

        $view->backurl = lw_url::get();
        $view->action = lw_url::get(array('logcmd' => 'resetpw', 'code' => $code, 'uid' => $uid));

        return utf8_decode($view->render());
    }

    private function buildSuccessView() {
        $view = new lw_view($this->basedir . "templates/password.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != 'en')
            $view->lang = "de";

        $view->notfound = false;
        $view->found = false;
        $view->success = true;
        $view->showpassworddialog = false;
        $view->backurl = lw_url::get();
        $view->loginurl = lw_url::get();

        return utf8_decode($view->render());
    }

    private function buildNotFoundView() {
        $view = new lw_view($this->basedir . "templates/password.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != 'en')
            $view->lang = "de";

        $view->notfound = true;
        $view->found = false;
        $view->success = false;
        $view->showpassworddialog = false;
        $view->backurl = lw_url::get();

        return utf8_decode($view->render());
    }
}