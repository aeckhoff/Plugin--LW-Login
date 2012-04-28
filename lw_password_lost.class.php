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

class lw_password_lost extends lw_object
{
    function __construct($params) 
    {
        $this->params = $params;
        $reg = lw_registry::getInstance();
        $this->fPost = $reg->getEntry("fPost");
        $this->config = $reg->getEntry("config");
        $this->in_auth = lw_in_auth::getInstance();
        $this->fGet = $reg->getEntry("fGet");        
    }
    
    private function sendHashMail($hashData) 
    {
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
        } 
        else {
            $subject = utf8_decode("Passwort zurücksetzen");
        }
        $to = filter_var($this->fPost->getRaw('lw_login_email'), FILTER_SANITIZE_EMAIL);
        mail($to, $subject, $view->render(), 'FROM:'.$this->config['pwlost']['from']);
    }    

    public function handlePasswordLost() 
    {
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
                } 
                else {
                    $this->sendHashMail($hashData);
                    $view->error = false;
                }
            } 
            else {
                $view->noemailerror = true;
            }
        } 
        else {
            $_SESSION['lw_password_lost_email'] = 1;
        }

        $view->action = lw_page::getInstance()->getUrl(array("logcmd" => "pwlost"));
        $view->lang = $this->params['lang'];
        $view->action = lw_page::getInstance()->getUrl(array("logcmd" => "pwlost"));
        $view->backurl = lw_page::getInstance()->getUrl(false, "logcmd");
        if ($this->params['lang'] != 'en') {
            $view->lang = "de";
        }
        return $view->render();
    }
    
    public function handleResetPassword() 
    {
        $code = $this->fGet->getAlnum('code');
        $uid = $this->fGet->getInt('uid');
        if ((strlen($code) < 10) || ($uid < 1)) {
            return $this->buildNotFoundView();
        }
        if (!$this->in_auth->checkPasswordHash($code, $uid)) {
            return $this->buildNotFoundView();
        }

        if ($this->fPost->getInt('password') == 1) {
            $ok = $this->resetPassword($code, $uid);

            if ($ok) {
                return $this->buildSuccessView();
            }
            return $this->buildPasswordView(true);
        }

        return $this->buildPasswordView();
    }    
    
    private function resetPassword() 
    {
        $pass1 = $this->fPost->getRaw('lw_login_pass_1');
        $pass2 = $this->fPost->getRaw('lw_login_pass_2');

        $code = $this->fGet->getAlnum('code');
        $uid = $this->fGet->getAlnum('uid');

        $pass1 = trim($pass1);
        $pass2 = trim($pass2);

        if ($pass1 != $pass2) {
            return false;
        }

        if (strlen($pass1) < 5) {
            return false;
        }

        $ok = $this->in_auth->resetPassword($code, $uid, $pass1);
        return $ok;
    }    
    
    private function buildPasswordView($error = false) 
    {
        $view = new lw_view($this->basedir . "templates/password.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != 'en') {
            $view->lang = "de";
        }

        $code = $this->fGet->getAlnum('code');
        $uid = $this->fGet->getAlnum('uid');

        $view->notfound = false;
        $view->found = true;
        $view->success = false;
        $view->showpassworddialog = true;
        $view->error = $error;

        $view->backurl = lw_page::getInstance()->getUrl();
        $view->action = lw_page::getInstance()->getUrl(array('logcmd' => 'resetpw', 'code' => $code, 'uid' => $uid));

        return utf8_decode($view->render());
    }    
    
    private function buildSuccessView() 
    {
        $view = new lw_view($this->basedir . "templates/password.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != 'en') {
            $view->lang = "de";
        }

        $view->notfound = false;
        $view->found = false;
        $view->success = true;
        $view->showpassworddialog = false;
        $view->backurl = lw_page::getInstance()->getUrl();
        $view->loginurl = lw_page::getInstance()->getUrl();

        return utf8_decode($view->render());
    }
    
    private function buildNotFoundView() 
    {
        $view = new lw_view($this->basedir . "templates/password.tpl.phtml");
        $view->lang = $this->params['lang'];
        if ($this->params['lang'] != 'en') {
            $view->lang = "de";
        }

        $view->notfound = true;
        $view->found = false;
        $view->success = false;
        $view->showpassworddialog = false;
        $view->loginurl = lw_page::getInstance()->getUrl();

        return utf8_decode($view->render());
    }      
}
