<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Users
*/

/**
 * main function
 * if user isn't logged in, direct him to getlogin screen
 * else to your account screen
 */
function users_user_main()
{
    // Security check
    if ((!pnUserLoggedIn()) || (!SecurityUtil::checkPermission('Users::', '::', ACCESS_READ))) {
        return LogUtil::registerPermissionError();
    }

    // The API function is called.
    $accountlinks = pnModAPIFunc('Users', 'user', 'accountlinks');

    if ($accountlinks == false) {
        return LogUtil::registerError(__('Error! No results found.'), 404);
    }

    // Create output object
    $pnRender = Renderer::getInstance('Users', false, null, true);

    // Assign the items to the template
    $pnRender->assign('accountlinks', $accountlinks);

    // Return the output that has been generated by this function
    return $pnRender->fetch('users_user_main.htm');
}

/**
 * display the base user form (login/lostpassword/register options)
 */
function users_user_view($args)
{
    // If has logged in, header to index.php
    if (pnUserLoggedIn()) {
        return pnRedirect(pnConfigGetVar('entrypoint', 'index.php'));
    }

    // create output object
    $pnRender = Renderer::getInstance('Users');

    // other vars
    $pnRender->assign(pnModGetVar('Users'));

    return $pnRender->fetch('users_user_view.htm');
}

/**
 * display the login form
 *
 * @param bool stop display the invalid username/password message
 * @param int redirectype type of redirect 0 = redirect to referer (default), 1 = redirect to current uri
 */
function users_user_loginscreen($args)
{
    // create output object
    $pnRender = Renderer::getInstance('Users');

    // we shouldn't get here if logged in already....
    if (pnUserLoggedIn()) {
        return pnRedirect(pnModURL('Users', 'user', 'main'));
    }

    // TODO C Appears to be unused If confirmed, it can be removed. ph
    /*    $redirecttype = (int)FormUtil::getPassedValue('redirecttype', isset($args['redirecttype']) ? $args['redirecttype'] : 0, 'GET');
    if ($redirecttype == 0) {
        $returnurl = pnServerGetVar('HTTP_REFERER');
    } else {
        $returnurl = pnGetCurrentURI();
    }
    if (empty($returnurl)) {
        $returnurl = pnGetBaseURL();
    }
    */

    $returnurl = FormUtil::getPassedValue('returnpage', null, 'GET');
    $confirmtou = (int)FormUtil::getPassedValue('confirmtou', isset($args['confirmtou']) ? $args['confirmtou'] : 0, 'GET');
    $changepassword = (int)FormUtil::getPassedValue('changepassword', isset($args['changepassword']) ? $args['changepassword'] : 0, 'GET');

    $passwordtext = ($changepassword == 1) ? __('Current password') : __('Password');

    // assign variables for the template
    $pnRender->assign('loginviaoption', pnModGetVar('Users', 'loginviaoption'));
    $pnRender->assign('seclevel', pnConfigGetVar('seclevel'));
    $pnRender->assign('allowregistration', pnModGetVar('Users', 'reg_allowreg'));
    $pnRender->assign('returnurl', $returnurl);
    // do we have to show a note about reconfirming the terms of use?
    if(pnModAvailable('legal') && (pnModGetVar('legal', 'termsofuse') || pnModGetVar('legal', 'privacypolicy'))) {
        $pnRender->assign('tou_active', pnModGetVar('legal', 'termsofuse', true));
        $pnRender->assign('pp_active',  pnModGetVar('legal', 'privacypolicy', true));
    } else {
        $confirmtou = 0;
    }
 
    // do we have to force the change of password?
    $pnRender->assign('changepassword', $changepassword);
    $pnRender->assign('confirmtou', $confirmtou);
    $pnRender->assign('passwordtext', $passwordtext);
    $pnRender->assign('use_password_strength_meter', pnModGetVar('Users', 'use_password_strength_meter'));

    return $pnRender->fetch('users_user_loginscreen.htm');
}

/**
 * set an underage flag and route the user back to the first user page
 */
function users_user_underage($args)
{
    LogUtil::registerError(__f('Sorry! You must be %s or over to register for a user account here.', pnModGetVar('Users', 'minage')));
    return pnRedirect(pnModURL('Users', 'user', 'view'));
}

/**
 * display the registration form
 */
function users_user_register($args)
{
    // If has logged in, header to index.php
    if (pnUserLoggedIn()) {
        return pnRedirect(pnConfigGetVar('entrypoint', 'index.php'));
    }

    $template = 'users_user_register.htm';
    // check if we've agreed to the age limit
    if (pnModGetVar('Users', 'minage') != 0 && !stristr(pnServerGetVar('HTTP_REFERER'), 'register')) {
        $template = 'users_user_checkage.htm';
    }

    // create output object
    $pnRender = Renderer::getInstance('Users', false);

    // other vars
    $modvars = pnModGetVar('Users');

    $pnRender->assign($modvars);
    $pnRender->assign('sitename', pnConfigGetVar('sitename'));
    $pnRender->assign('legal',    pnModAvailable('legal'));
    $pnRender->assign('tou_active', pnModGetVar('legal', 'termsofuse', true));
    $pnRender->assign('pp_active',  pnModGetVar('legal', 'privacypolicy', true));

    return $pnRender->fetch($template);
}

/**
 * display the lost password form
 */
function users_user_lostpassword($args)
{
    // we shouldn't get here if logged in already....
    if (pnUserLoggedIn()) {
        return pnRedirect(pnModURL('Users', 'user', 'main'));
    }

    // create output object
    $pnRender = Renderer::getInstance('Users');
    $pnRender->assign('allowregistration', pnModGetVar('Users', 'reg_allowreg'));

    return $pnRender->fetch('users_user_lostpassword.htm');
}

/**
 * login function
 * login a user. if username or password is wrong, display error msg.
 */
function users_user_login()
{
    // we shouldn't get here if logged in already....
    if (pnUserLoggedIn()) {
        return pnRedirect(pnModURL('Users', 'user', 'main'));
    }

    if (!SecurityUtil::confirmAuthKey('Users')) {
        return LogUtil::registerAuthidError(pnModURL('Users','user','loginscreen'));
    }

    $uname      = FormUtil::getPassedValue ('uname');
    $email      = FormUtil::getPassedValue ('email');
    $pass       = FormUtil::getPassedValue ('pass');
    $url        = FormUtil::getPassedValue ('url');
    $rememberme = FormUtil::getPassedValue ('rememberme', '');

    $userid = pnUserGetIDFromName($uname);

    $userstatus = pnUserGetVar('activated', $userid);
    $tryagain = false;
    $confirmtou = 0;
    $changepassword = 0;
    if (($userstatus == 2 || $userstatus == 6) && pnModAvailable('legal') && (pnModGetVar('legal', 'termsofuse', true) || pnModGetVar('legal', 'privacypolicy', true))) {
        $confirmtou = 1;
        $touaccepted = (int)FormUtil::getPassedValue('touaccepted', 0, 'GETPOST');
        if ($touaccepted<>1) {
            // user had to accept the terms of use, but didn't
            $tryagain = true;
        }
    }

    // the current password must be valid
    $pnuser_current_pass = pnUserGetVar('pass', $userid);
    $pnuser_hash_number = pnUserGetVar('hash_method', $userid);
    $hashmethodsarray   = pnModAPIFunc('Users', 'user', 'gethashmethods', array('reverse' => true));           
    $passhash = hash($hashmethodsarray[$pnuser_hash_number], $pass);
    if($passhash != $pnuser_current_pass) {
        $errormsg = __('Sorry! The current password you entered is not correct. Please correct your entry and try again.');
        $tryagain = true;
    }

    if ($userstatus == 4 || $userstatus == 6) {
        $changepassword = 1;
        $validnewpass = true;
        $newpass = FormUtil::getPassedValue('newpass', null, 'POST');
        $confirmnewpass = FormUtil::getPassedValue('confirmnewpass', null, 'POST');
        // checks if the new password is valid
        // the new password must be different of the current password
        if($pass == $newpass && $validnewpass) {
            $errormsg = __('Sorry! The new and the current passwords must be different. Please correct your entries and try again.');
            $validnewpass = false;
        }

        // check if the new password satisfy the requirements
        $minpass = pnModGetVar('Users', 'minpass');
        if (!empty($newpass) && strlen($newpass) < $minpass && $validnewpass) {
            $errormsg = _fn('Your password must be at least %s character long.', 'Your password must be at least %s characters long.', $minpass, $minpass);
            $validnewpass = false;
        }

        // checks if the new password and the repeated new password are the same
        if(($newpass != $confirmnewpass) && $validnewpass) {
            $errormsg = __('Sorry! The two passwords you entered do not match. Please correct your entries and try again.');
            $validnewpass = false;
        }

        // checks if the new password and the repeated new password are the same
        if(empty($newpass)) {
            $validnewpass = false;
        }

        if (!$validnewpass) {
            // user password change is incorrect
            $tryagain = true;
        }
    }

    if($tryagain) {
        // user had to accept the terms of use, but didn't
        if($errormsg == '') { $errormsg = __('Error! Log-in was not completed. Please read the information below.');}
        return LogUtil::registerError($errormsg , 403, pnModURL('Users','user','loginscreen',
                                                                array('confirmtou' => $confirmtou,
                                                                      'changepassword' => $changepassword,
                                                                      'returnpage' => $url)));        
    } else {
        if($userstatus == 4 || $userstatus == 6) {
            // change the user's password
            $pnuser_hash_number = pnUserGetVar('hash_method', $userid);
            $hashmethodsarray   = pnModAPIFunc('Users', 'user', 'gethashmethods', array('reverse' => true));           
            $newpasshash = hash($hashmethodsarray[$pnuser_hash_number], $newpass);
            pnUserSetVar('pass', $newpasshash, $userid);
            $pass = $newpass;
        }
        pnUserSetVar('activated', 1, $userid);
    }

    $loginoption    = pnModGetVar('Users', 'loginviaoption');
    $login_redirect = pnModGetVar('Users', 'login_redirect');

    if (pnUserLogIn((($loginoption==1) ? $email : $uname), $pass, $rememberme)) {
        // start login hook
        $uid = pnUserGetVar('uid');
        pnModCallHooks('zikula', 'login', $uid, array('module' => 'zikula'));
        if ($login_redirect == 1) {
            // WCAG compliant login
            return pnRedirect($url);
        } else {
            // meta refresh
            users_print_redirectpage(__('You are being logged-in. Please wait...'), $url);
        }
        return true;
    } else {
        LogUtil::registerError(__('Sorry! Unrecognised user name or password. Please try again.'));
        $reg_verifyemail = pnModGetVar('Users', 'reg_verifyemail');
        if ($reg_verifyemail == 2) {
            LogUtil::registerError(__('Notice: If you have just registered a new account then please check your e-mail and activate your account before trying to log in.'));
        }
        return pnRedirect(pnModURL('Users','user','loginscreen', array('returnpage' => urlencode($url))));
    }
}

/**
 * logout function
 * log a user out.
 */
function users_user_logout()
{
    $login_redirect = pnModGetVar('Users', 'login_redirect');

    // start logout hook
    $uid = pnUserGetVar('uid');
    pnModCallHooks('zikula', 'logout', $uid, array('module' => 'zikula'));
    if (pnUserLogOut()) {
        if ($login_redirect == 1) {
            // WCAG compliant logout - we redirect to index.php because
            // we might no have the permission for the recent site any longer
            return pnRedirect(pnConfigGetVar('entrypoint', 'index.php'));
        } else {
            // meta refresh
            users_print_redirectpage(__('Done! You have been logged out.'));
        }
    } else {
        LogUtil::registerError(__('Error! You have not been logged out.'));
        return pnRedirect(pnConfigGetVar('entrypoint', 'index.php'));
    }

    return true;
}

/**
 * users_user_finishnewuser()
 *
 */
function users_user_finishnewuser()
{
    if (!SecurityUtil::confirmAuthKey('Users')) {
        return LogUtil::registerAuthidError(pnModURL('Users','user','register'));
    }

    $uname          = FormUtil::getPassedValue ('uname', null, 'POST');
    $agreetoterms   = FormUtil::getPassedValue ('agreetoterms', null, 'POST');
    $email          = FormUtil::getPassedValue ('email', null, 'POST');
    $vemail         = FormUtil::getPassedValue ('vemail', null, 'POST');
    $pass           = FormUtil::getPassedValue ('pass', null, 'POST');
    $vpass          = FormUtil::getPassedValue ('vpass', null, 'POST');
    $user_viewemail = FormUtil::getPassedValue ('user_viewmail', null, 'POST');
    $reg_answer     = FormUtil::getPassedValue ('reg_answer', null, 'POST');

    if (pnModGetVar('Users', 'lowercaseuname', false)) {
        $uname = strtolower($uname);
    }

    // some defaults for error detection and redirection
    $msgtype = 'error';
    $redirectfunc = 'loginscreen';

    // Verify dynamic user data
    if (pnModGetVar('Users', 'reg_optitems') == 1) {
        $profileModule = pnConfigGetVar('profilemodule', '');
        if (!empty($profileModule) && pnModAvailable($profileModule)) {

            // any Profile module needs this function
            $checkrequired = pnModAPIFunc($profileModule, 'user', 'checkrequired');

            if ($checkrequired) {
                /*! %s is a comma separated list of fields that were left blank */
                $message = __f('Error! One or more required fields were left blank or incomplete (%s).', $checkrequired['translatedFieldsStr']);

                return LogUtil::registerError($message, null, pnModURL('Users', 'user', 'register'));
            }
        }
    }

    // because index.php use $name var $name can not get correct value. [class007]
    $name         = $uname;
    $commentlimit = (int)pnModGetVar('Users', 'commentlimit', 0);
    $storynum     = (int)pnModGetVar('Users', 'storyhome', 10);
    $minpass      = (int)pnModGetVar('Users', 'minpass', 5);
    $user_regdate = DateUtil::getDatetime();

    // TODO: add require check for dynamics.
    $checkuser = pnModAPIFunc('Users', 'user', 'checkuser',
                              array('uname'        => $uname,
                                    'email'        => $email,
                                    'agreetoterms' => $agreetoterms));

    // if errorcode != 1 then return error msgs
    if ($checkuser != 1) {
        switch ($checkuser)
        {
            case -1:
                $message = __('Sorry! You have not been granted access to this module.');
                break;
            case 2:
                $message =  __('Sorry! The e-mail address you entered was incorrectly formatted or is unacceptable for other reasons. Please correct your entry and try again.');
                break;
            case 3:
                $message =  __('Error! Please click on the checkbox to accept the site\'s \'Terms of use\' and \'Privacy policy\'.');
                break;
            case 4:
                $message =  __('Sorry! The user name you entered is not acceptable. Please correct your entry and try again.');
                break;
            case 5:
                $message =  __('Sorry! The user name you entered is too long. The maximum length is 25 characters.');
                break;
            case 6:
                $message =  __('Sorry! The user name you entered is reserved and cannot be registered. Please choose another name and try again.');
                break;
            case 7:
                $message =  __('Sorry! Your user name cannot contain spaces. Please correct your entry and try again.');
                break;
            case 8:
                $message =  __('Sorry! This user name has already been registered. Please choose another name and try again.');
                break;
            case 9:
                $message =  __('Sorry! This e-mail address has already been registered, and it cannot be used again for creating another account.');
                break;
            case 11:
                $message =  __('Sorry! Your user agent is not accepted for registering an account on this site.');
                break;
            case 12:
                $message =  __('Sorry! E-mail addresses from the domain you entered are not accepted for registering an account on this site.');
                break;
            default:
                $message =  __('Sorry! You have not been granted access to this module.');
        } // switch

        return LogUtil::registerError($message, null, pnModURL('Users', 'user', 'register'));
    }

    if ($email !== $vemail) {
        $message = __('Sorry! You did not enter the same e-mail address in each box. Please correct your entry and try again.');
    }

    $modvars = pnModGetVar('Users');

    if (!$modvars['reg_verifyemail'] || $modvars['reg_verifyemail'] == 2) {
        if ((isset($pass)) && ("$pass" != "$vpass")) {
            $message = __('Error! You did not enter the same password in each password field. Please enter the same password once in each password field (this is required for verification).');

        } elseif (isset($pass) && (strlen($pass) < $minpass)) {
            $message =  _fn('Your password must be at least %s character long', 'Your password must be at least %s characters long', $minpass);

        } elseif (empty($pass) && !pnModGetVar('Users', 'reg_verifyemail')) {
            $message =  __('Error! Please enter a password.');
        }
    }

    if ($modvars['reg_question'] != '' && $modvars['reg_answer'] != '') {
        if ($reg_answer != $modvars['reg_answer']) {
            $message = __('Sorry! You gave the wrong answer to the anti-spam registration question. Please correct your entry and try again.');
        }
    }

    if (isset($message)) {
        return LogUtil::registerError($message, null, pnModURL('Users', 'user', 'register'));
    }

    // TODO: Clean up
    $registered = pnModAPIFunc('Users', 'user', 'finishnewuser',
                               array('uname'         => $uname,
                                     'pass'          => $pass,
                                     'email'         => $email,
                                     'user_regdate'  => $user_regdate,
                                     'storynum'      => $storynum,
                                     'commentlimit'  => $commentlimit));

    if (!$registered) {
        LogUtil::registerError(__('Error! The registration process failed. Please contact the site administrator.'));
    } else {
        if ((int)pnModGetVar('Users', 'moderation') == 1) {
            LogUtil::registerStatus(__('Done! Thanks for registering! Your application has been submitted for approval.'));
            $pnr = Renderer::getInstance('Users');
            return $pnr->fetch('users_user_registrationfinished.htm');
        } else {
            LogUtil::registerStatus(__('Done! You are now a registered user. You should receive your user account details (including your password) at the e-mail address you entered.'));
            if (pnModGetVar('Users', 'reg_verifyemail') == 2) {
                LogUtil::registerStatus(__('Please use the link in the e-mail message to activate your account.'));
            }
            return pnRedirect(pnModURL('Users', 'user', $redirectfunc));
        }
    }

    return pnRedirect(pnGetHomepageURL());
}

/**
 * users_user_mailpasswd()
 */
function users_user_mailpasswd()
{
    $uname = FormUtil::getPassedValue ('uname', null, 'POST');
    $email = FormUtil::getPassedValue ('email', null, 'POST');
    $code  = FormUtil::getPassedValue ('code',  null, 'POST');
    SessionUtil::delVar('lostpassword_uname');
    SessionUtil::delVar('lostpassword_email');
    SessionUtil::delVar('lostpassword_code');

    if (!empty($code)) {
        SessionUtil::setVar('lostpassword_code', $code);
    }
    if (!$email && !$uname) {
        LogUtil::registerError(__f('Error! User name and e-mail address fields are empty.'));
        return pnRedirect(pnModURL('Users', 'user', 'lostpassword'));
    }

    // save username and password for redisplay
    SessionUtil::setVar('lostpassword_uname', $uname);
    SessionUtil::setVar('lostpassword_email', $email);

    if (!empty($email) && !empty($uname)) {
        LogUtil::registerError(__f('Error! Please enter a user name OR e-mail address, no both of them.'));
        return pnRedirect(pnModURL('Users', 'user', 'lostpassword'));
    }

    //0=DatabaseError 1=WrongCode 2=NoSuchUsernameOrEmailAddress 3=PasswordMailed 4=ConfirmationCodeMailed
    $returncode = pnModAPIFunc('Users', 'user', 'mailpasswd',
                               array('uname' => $uname,
                                     'email' => $email,
                                     'code'  => $code));

    if (!empty($email)) {
        $who = $email;
    }
    if (!empty($uname)) {
        $who = $uname;
    }

    switch ($returncode)
    {
        case 0:
            $message = __('Error! Could not save your changes.');
            break;
        case 1:
            $message = __("Error! The code that you've enter is invalid.");
            break;
        case 2:
            $message = __('Sorry! Could not find any matching user account.');
            break;
        case 3:
            $message = __f('Done! Password e-mailed for %s.', $who);
            SessionUtil::delVar('lostpassword_uname');
            SessionUtil::delVar('lostpassword_email');
            SessionUtil::delVar('lostpassword_code');
            break;
        case 4:
            $message = __f('Done! The confirmation code for %s has been sent by e-mail.', $who);
            break;
        default:
            return false;
    }

    if ($returncode < 3) {
        LogUtil::registerError($message);
    } else {
        LogUtil::registerStatus($message);
    }

    switch ($returncode)
    {
        case 3:
            return pnRedirect(pnModURL('Users', 'user', 'loginscreen'));
            break;
        default:
            return pnRedirect(pnModURL('Users', 'user', 'lostpassword'));
    }
}

/**
 * users_user_activation($args)
 *
 * Get rid of user activation Link
 *
 */
function users_user_activation($args)
{
    $code = base64_decode(FormUtil::getPassedValue('code', (isset($args['code']) ? $args['code'] : null), 'GETPOST'));
    $code = explode('#', $code);

    if (!isset($code[0]) || !isset($code[1])) {
        return LogUtil::registerError(__('Error! Could not activate your account. Please contact the site administrator.'));
    }
    $uid = $code[0];
    $code = $code[1];

    // Get user Regdate
    $regdate = pnUserGetVar('user_regdate', $uid);

    // Checking length in case the date has been stripped from its space in the mail.
    if (strlen($code) == 18) {
        if (!strpos($code, ' ')) {
            $code = substr($code, 0, 10) . ' ' . substr($code, -8);
        }
    }

    if (hash('md5', $regdate) == hash('md5', $code)) {
        $returncode = pnModAPIFunc('Users', 'user', 'activateuser',
                                   array('uid'     => $uid,
                                         'regdate' => $regdate));

        if (!$returncode) {
            return LogUtil::registerError(__('Error! Could not activate your account. Please contact the site administrator.'));
        }
        LogUtil::registerStatus(__('Done! Account activated.'));
        return pnRedirect(pnModURL('Users', 'user', 'loginscreen'));
    } else {
        return LogUtil::registerError(__('Sorry! You entered an invalid confirmation code. Please correct your entry and try again.'));
    }
}

/**
 * print a redirect page
 * original function name is 'redirect_index' in NS-User/tools.php
 *
 * @access private
 */
function users_print_redirectpage($message, $url)
{
    $pnRender = Renderer::getInstance('Users');
    $url = (!isset($url) || empty($url)) ? pnConfigGetVar('entrypoint', 'index.php') : $url;

    // check the url
    if (substr($url, 0, 1) == '/') {
        // Root-relative links
        $url = 'http'.(pnServerGetVar('HTTPS')=='on' ? 's' : '').'://'.pnServerGetVar('HTTP_HOST').$url;
    } elseif (!preg_match('!^(?:http|https):\/\/!', $url)) {
        // Removing leading slashes from redirect url
        $url = preg_replace('!^/*!', '', $url);
        // Get base URL and append it to our redirect url
        $baseurl = pnGetBaseURL();
        $url = $baseurl.$url;
    }

    $pnRender->assign('ThemeSel', pnConfigGetVar('Default_Theme'));
    $pnRender->assign('url', $url);
    $pnRender->assign('message', $message);
    $pnRender->assign('stylesheet', ThemeUtil::getModuleStylesheet('Users'));
    $pnRender->assign('redirectmessage', __('If you are not automatically re-directed then please click here.'));
    $pnRender->display('users_user_redirectpage.htm');
    return true;
}

/**
 * login to disabled site
 *
 */
function users_user_siteofflogin()
{
    // do not process if the site is enabled
    if (!pnConfigGetVar('siteoff', false)) {
        $path = dirname(pnServerGetVar('PHP_SELF'));
        $path = str_replace('\\', '/', $path);
        return pnRedirect($path . '/' . pnConfigGetVar('entrypoint', 'index.php'));
    }

    $user = FormUtil::getPassedValue('user', null, 'POST');
    $pass = FormUtil::getPassedValue('pass', null, 'POST');
    $rememberme = FormUtil::getPassedValue('rememberme', false, 'POST');

    pnUserLogIn($user, $pass, $rememberme);

    if (!SecurityUtil::checkPermission('Settings::', 'SiteOff::', ACCESS_ADMIN)) {
        pnUserLogOut();
    }

    $path = dirname(pnServerGetVar('PHP_SELF'));
    $path = str_replace('\\', '/', $path);
    return pnRedirect($path . '/' . pnConfigGetVar('entrypoint', 'index.php'));
}

/**
 * display the configuration options for the users block
 *
 */
function users_user_usersblock()
{
    $blocks = pnModAPIFunc('Blocks', 'user', 'getall');
    $mid = pnModGetIDFromName('Users');
    $found = false;
    foreach ($blocks as $block) {
        if ($block['mid'] == $mid && $block['bkey'] == 'user') {
            $found = true;
            break;
        }
    }

    if (!$found) {
        return LogUtil::registerPermissionError();
    }

    $pnRender = Renderer::getInstance('Users');
    $pnRender->assign(pnUserGetVars(pnUserGetVar('uid')));
    return $pnRender->fetch('users_user_usersblock.htm');
}

/**
 * update users block
 *
 */
function users_user_updateusersblock()
{
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    $blocks = pnModAPIFunc('Blocks', 'user', 'getall');
    $mid = pnModGetIDFromName('Users');
    $found = false;
    foreach ($blocks as $block) {
        if ($block['mid'] == $mid && $block['bkey'] == 'user') {
            $found = true;
            break;
        }
    }

    if (!$found) {
        return LogUtil::registerPermissionError();
    }

    $uid = pnUserGetVar('uid');
    $ublockon = (bool)FormUtil::getPassedValue('ublockon', false, 'POST');
    $ublock = (string)FormUtil::getPassedValue('ublock', '', 'POST');

    pnUserSetVar('ublockon', $ublockon);
    pnUserSetVar('ublock', $ublock);

    LogUtil::registerStatus(__('Done! Saved custom block.'));
    return pnRedirect(pnModURL('Users'));
}

/**
 * change your password
 *
 */
function Users_user_changepassword()
{
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    $changepassword = pnModGetVar('Users', 'changepassword', 1);
    if ($changepassword <> 1) {
        return pnRedirect('Users', 'user', 'main');
    }

    // Create output object
    $pnRender = Renderer::getInstance('Users', false, null, true);

    // assign vars
    $pnRender->assign('use_password_strength_meter', pnModGetVar('Users', 'use_password_strength_meter'));

    // Return the output that has been generated by this function
    return $pnRender->fetch('users_user_changepassword.htm');
}

/**
 * update the password
 *
 */
function Users_user_updatepassword()
{
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    $uservars = pnModGetVar('Users');
    if ($uservars['changepassword'] <> 1) {
        return pnRedirect('Users', 'user', 'main');
    }

    $oldpassword        = FormUtil::getPassedValue('oldpassword', '', 'POST');
    $newpassword        = FormUtil::getPassedValue('newpassword', '', 'POST');
    $newpasswordconfirm = FormUtil::getPassedValue('newpasswordconfirm', '', 'POST');

    $uname = pnUserGetVar('uname');
    // password existing check doesn't apply to HTTP(S) based login
    if (!isset($uservars['loginviaoption']) || $uservars['loginviaoption'] == 0) {
        $user = DBUtil::selectObjectByID('users', $uname, 'uname', null, null, null, false, 'lower');
    } else {
        $user = DBUtil::selectObjectByID('users', $uname, 'email', null, null, null, false, 'lower');
    }

    $upass = $user['pass'];
    $pnuser_hash_number = $user['hash_method'];
    $hashmethodsarray   = pnModAPIFunc('Users', 'user', 'gethashmethods', array('reverse' => true));

    $opass = hash($hashmethodsarray[$pnuser_hash_number], $oldpassword);

    if (empty($oldpassword) || $opass != $upass) {
        return LogUtil::registerError(__('Sorry! The password you entered is not correct. Please correct your entry and try again.'), null, pnModURL('Users', 'user', 'changepassword'));
    }

    $minpass = pnModGetVar('Users', 'minpass');
    if (strlen($newpassword) < $minpass) {
        return LogUtil::registerError(_fn('Your password must be at least %s character long.', 'Your password must be at least %s characters long.', $minpass, $minpass), null, pnModURL('Users', 'user', 'changepassword'));
    }

    // check if the new password and the confirmation are identical
    if ($newpassword != $newpasswordconfirm) {
        return LogUtil::registerError(__('Sorry! The two passwords you entered do not match. Please correct your entries and try again.'), null, pnModURL('Users', 'user', 'changepassword'));
    }

    // set the new password
    pnUserSetPassword($newpassword);

    LogUtil::registerStatus(__('Done! Saved your new password.'));
    return pnRedirect(pnModURL('Users', 'user', 'main'));
}

/**
 * change your email address
 */
function Users_user_changeemail()
{
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    $changeemail = pnModGetVar('Users', 'changeemail', 1);
    if ($changeemail <> 1) {
        return pnRedirect('Users', 'user', 'main');
    }

    // Create output object
    $pnRender = Renderer::getInstance('Users', false, null, true);

    // Return the output that has been generated by this function
    return $pnRender->fetch('users_user_changeemail.htm');
}

/**
 * update the email address
 */
function Users_user_updateemail()
{
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    $uservars = pnModGetVar('Users');
    if ($uservars['changeemail'] <> 1) {
        return pnRedirect('Users', 'user', 'main');
    }

    $newemail = FormUtil::getPassedValue('newemail', '', 'POST');

    $checkuser = pnModAPIFunc('Users', 'user', 'checkuser',
                              array('uname'        => pnUserGetVar('uname'),
                                    'email'        => $newemail,
                                    'agreetoterms' => true));

    // check email related errors only
    if (in_array($checkuser, array(-1, 2, 9, 11, 12))) {
        switch($checkuser)
        {
            case -1:
                $message = __('Sorry! You have not been granted access to this module.');
                break;
            case 2:
                $message =  __('Sorry! The e-mail address you entered was incorrectly formatted or is unacceptable for other reasons. Please correct your entry and try again.');
                break;
            case 9:
                $message =  __('Sorry! This e-mail address has already been registered, and it cannot be used again for creating another account.');
                break;
            case 11:
                $message =  __('Sorry! Your user agent is not accepted for registering an account on this site.');
                break;
            case 12:
                $message =  __('Sorry! E-mail addresses from the domain you entered are not accepted for registering an account on this site.');
                break;
            default:
                $message =  __('Sorry! You have not been granted access to this module.');
        } // switch
        return LogUtil::registerError($message, null, pnModURL('Users', 'user', 'changeemail'));
    }

    // save the provisional email until confimation
    if(!pnModAPIFunc('Users', 'user', 'savepreemail',
                    array('newemail' => $newemail))) {
        return LogUtil::registerError(__('Error! It has not been possible to change the e-mail address.'), null, pnModURL('Users', 'user', 'changeemail'));
    }

    LogUtil::registerStatus(__('Done! You will receive an e-mail to your new e-mail address to confirm the change.'));
    return pnRedirect(pnModURL('Users', 'user', 'main'));
}

/**
 * change your language
 */
function Users_user_changelang()
{
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    // Create output object
    $pnRender = Renderer::getInstance('Users', false);

    // Assign the languages
    $pnRender->assign('languages', ZLanguage::getInstalledLanguageNames());
    $pnRender->assign('usrlang', ZLanguage::getLanguageCode());

    // Return the output that has been generated by this function
    return $pnRender->fetch('users_user_changelang.htm');
}

/**
 * confirm the update of the email address
 */
function Users_user_confirmchemail($args)
{
    $confirmcode = FormUtil::getPassedValue('confirmcode', isset($args['confirmcode']) ? $args['confirmcode'] : null, 'GET');
    if (!pnUserLoggedIn()) {
        return LogUtil::registerPermissionError();
    }

    // get user new email that is waiting for confirmation
    $preemail = pnModAPIFunc('Users', 'user', 'getuserpreemail');

    // the e-mail change is valid during 5 days
    $fiveDaysAgo =  time() - 5*24*60*60;

    if(!$preemail || $confirmcode != $preemail['comment'] || $preemail['dynamics'] < $fiveDaysAgo) {
        LogUtil::registerError(__('Error! Your e-mail has not been found. After your request you have five days to confirm the new e-mail address.'));
        return pnRedirect(pnModURL('Users', 'user', 'main'));        
    }

    // user and confirmation code are correct. set the new email
    pnUserSetVar('email', $preemail['email']);

    // the preemail record is deleted
    pnModAPIFunc('Users', 'admin', 'deny',
                array('userid' => $preemail['tid']));
    
    LogUtil::registerStatus(__('Done! Changed your e-mail address.'));
    return pnRedirect(pnModURL('Users', 'user', 'main'));    
}
