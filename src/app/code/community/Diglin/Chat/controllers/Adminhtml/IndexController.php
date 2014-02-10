<?php
/**
 * Diglin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Diglin
 * @package     Diglin_Chat
 * @copyright   Copyright (c) 2011-2014 Diglin (http://www.diglin.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Diglin_Chat_Adminhtml_IndexController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_forward('account');
    }

    /**
     *
     */
    public function dashboardAction()
    {
        // @deprecated for security reason on Zopim side, we forward to
        //$this->loadLayout()->renderLayout();
        $this->_redirectUrl(Diglin_Chat_Helper_Data::ZOPIM_DASHBOARD_URL);
    }

    /**
     * Code partially taken from the old Zopim Live Chat Module
     * Lots has been improved but can still better be improved
     * @todo split into several actions if possible
     */
    public function accountAction()
    {
        $this->loadLayout();

        $chatAccountBlock = $this->getLayout()->getBlock('zopim_account');

        $key = Mage::getStoreConfig('chat/chatconfig/key');
        $username = Mage::getStoreConfig('chat/chatconfig/username');
        $salt = Mage::getStoreConfig('chat/chatconfig/salt');
        $useSSL = Mage::getStoreConfig('chat/chatconfig/use_ssl');

        $zopimObject = new Varien_Object(array(
            'key' => $key,
            'username' => $username,
            'salt' => $salt,
            'use_ssl' => $useSSL
        ));

        $error = array();
        $gotologin = 0;

        if ($this->getRequest()->getParam('deactivate') == "yes") {
            $zopimObject->setSalt(null);
            $zopimObject->setKey('zopim');
        } else if ($this->getRequest()->getParam('zopimusername') != "") {
            // logging in
            if ($this->getRequest()->getParam('zopimUseSSL') != "") {
                $zopimObject->setUseSsl(true);
            } else {
                $zopimObject->setUseSsl(false);
            }

            $zopimusername = $this->getRequest()->getParam('zopimusername');
            $zopimpassword = $this->getRequest()->getParam('zopimpassword');

            $logindata = array(
                "email"     => $zopimusername,
                "password"  => $zopimpassword
            );

            $loginresult = Zend_Json::decode(Mage::helper('chat')->doPostRequest(Diglin_Chat_Helper_Data::ZOPIM_LOGIN_URL, $logindata, $zopimObject->getUseSsl()));

            Mage::log($loginresult, Zend_Log::DEBUG);

            if (isset($loginresult["error"])) {
                $error["login"] = $this->__("<b>Could not log in to Zopim. Please check your login details. If problem persists, try connecting without SSL enabled.</b>");
                $gotologin = 1;
                $zopimObject->setSalt(null);
            } elseif (isset($loginresult["salt"])) {

                $zopimObject->setUsername($zopimusername);
                $zopimObject->setSalt($loginresult["salt"]);

                $account = Zend_Json::decode(Mage::helper('chat')->doPostRequest(Diglin_Chat_Helper_Data::ZOPIM_GETACCOUNTDETAILS_URL, array("salt" => $loginresult["salt"]), $zopimObject->getUseSsl()));

                Mage::log($account, Zend_Log::DEBUG);

                if (isset($account)) {
                    $zopimObject->setKey($account["account_key"]);
//                    if ($zopimObject->getGreetings() == '') {
//                        $zopimObject->setGreetings(Zend_Json::encode($account["settings"]["greetings"]));
//                    }
                }
            } else {
                $zopimObject->setSalt(null);
                $error["login"] = $this->__("<b>Could not log in to Zopim. We were unable to contact Zopim servers. Please check with your server administrator to ensure that <a href='http://www.php.net/manual/en/book.curl.php'>PHP Curl</a> is installed and permissions are set correctly.</b>");
            }
        } else if ($this->getRequest()->getParam('zopimfirstname') != "") {

            if ($this->getRequest()->getParam('zopimUseSSL') != "") {
                $zopimObject->setUseSsl(true);
            } else {
                $zopimObject->setUseSsl(false);
            }

            $createdata = array(
                "email" => $this->getRequest()->getParam('zopimnewemail'),
                "first_name" => $this->getRequest()->getParam('zopimfirstname'),
                "last_name" => $this->getRequest()->getParam('zopimlastname'),
                "display_name" => $this->getRequest()->getParam('zopimfirstname') . " " . $this->getRequest()->getParam('zopimlastname'),
                "eref" => "",
                "source" => "magento",
                "recaptcha_challenge_field" => $this->getRequest()->getParam('recaptcha_challenge_field'),
                "recaptcha_response_field" => $this->getRequest()->getParam('recaptcha_response_field')
            );

            $signupresult = Zend_Json::decode(Mage::helper('chat')->doPostRequest(Diglin_Chat_Helper_Data::ZOPIM_SIGNUP_URL, $createdata, $zopimObject->getUseSsl()));
            if (isset($signupresult["error"])) {
                $error["auth"] = $this->__("Error during activation: <b>" . $signupresult["error"] . "</b> Please try again.");
            } else if (isset($signupresult["account_key"])) {
                $message = $this->__("<b>Thank you for signing up. Please check your mail for your password to complete the process. </b>");
                $gotologin = 1;
            } else {
                $error["auth"] = $this->__("<b>Could not activate account. The Magento installation was unable to contact Zopim servers. Please check with your server administrator to ensure that <a href='http://www.php.net/manual/en/book.curl.php'>PHP Curl</a> is installed and permissions are set correctly.</b>");
            }
        }
        //$this->_zmodel->save();

        if ($zopimObject->getKey() != "" && $zopimObject->getKey() != "zopim") {

            if (isset($account)) {
                $accountDetails = $account;
            } else {
                $accountDetails = Zend_Json::decode(Mage::helper('chat')->doPostRequest(Diglin_Chat_Helper_Data::ZOPIM_GETACCOUNTDETAILS_URL, array("salt" => $zopimObject->getSalt()), $zopimObject->getUseSsl()));
            }

            if (!isset($accountDetails) || isset($accountDetails["error"])) {
                $gotologin = 1;
                $error["auth"] = $this->__('Account no longer linked! We could not verify your Zopim account. Please check your password and try again.');
            } else {
                $chatAccountBlock->setIsAuthenticated(true);
            }
        }

        if (isset($error["auth"])) {
            $this->_getSession()->addError($error["auth"]);
        } else if (isset($error["login"])) {
            $this->_getSession()->addError($error["login"]);
        } else if (isset($message)) {
            $this->_getSession()->addSuccess($message);
        }

        if ($chatAccountBlock->getIsAuthenticated()) {
            if ($accountDetails["package_id"] == "trial") {
                $accountDetails["package_id"] = "Free Lite Package + 14 Days Full-features";
            } else {
                $accountDetails["package_id"] .= " Package";
            }
        } else {
            if ($this->getRequest()->getParam('zopimfirstname')) {
                $chatAccountBlock->setWasChecked('checked');
            }

            if (!$chatAccountBlock->getIsAuthenticated() && $gotologin != 1) {
                $chatAccountBlock->setShowSignup('showSignup(1);');
            } else {
                $chatAccountBlock->setShowSignup('showSignup(0);');
            }
        }

        $chatAccountBlock->setUsername($zopimObject->getUsername());

        if (isset($accountDetails)) {
            $chatAccountBlock->setAccountDetails($accountDetails["package_id"]);
        }

        // Save values into configuration
        $config = Mage::getConfig();

        if ($zopimObject->getKey() && $zopimObject->getKey() != 'zopim') {
            $config->saveConfig('chat/chatconfig/enabled', 1, 'default', 0);
        }

        if ($zopimObject->getKey() == 'zopim') {
            $zopimObject->setKey(null);
            $config->saveConfig('chat/chatconfig/enabled', 0, 'default', 0);
        }

        $config->saveConfig('chat/chatconfig/key', $zopimObject->getKey(), 'default', 0);
        $config->saveConfig('chat/chatconfig/username', $zopimObject->getUsername(), 'default', 0);
        $config->saveConfig('chat/chatconfig/salt', $zopimObject->getSalt(), 'default', 0);
        $config->saveConfig('chat/chatconfig/use_ssl', $zopimObject->getUseSsl(), 'default', 0);

        Mage::app()->cleanCache(array(Mage_Core_Model_Config::CACHE_TAG));

        $this->_initLayoutMessages('adminhtml/session');
        $this->renderLayout();
    }
}
