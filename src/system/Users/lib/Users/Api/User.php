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
 * The User API provides system-level and database-level functions for user-initiated actions;
 * this class provides those functions for the Users module.
 *
 * @package Zikula
 * @subpackage Users
 */
class Users_Api_User extends Zikula_Api
{
    /**
     * Get all users (for which the current user has permission to read).
     *
     * @param array $args All parameters passed to this function.
     *                    $args['letter']   (string) The first letter of the set of user names to return.
     *                    $args['starnum']  (int)    First item to return (optional).
     *                    $args['numitems'] (int)    Number if items to return (optional).
     *
     * @return array An array of users, or false on failure.
     */
    public function getAll($args)
    {
        // Optional arguments.
        $startnum = (isset($args['startnum']) && is_numeric($args['startnum'])) ? $args['startnum'] : 1;
        $numitems = (isset($args['numitems']) && is_numeric($args['numitems'])) ? $args['numitems'] : -1;

        // Security check
        if (!SecurityUtil::checkPermission('Users::', '::', ACCESS_OVERVIEW)) {
            return false;
        }

        $permFilter = array();
        // corresponding filter permission to filter anonymous in admin view:
        // Administrators | Users:: | Anonymous:: | None
        $permFilter[] = array('realm' => 0,
                'component_left'   => 'Users',
                'component_middle' => '',
                'component_right'  => '',
                'instance_left'    => 'uname',
                'instance_middle'  => '',
                'instance_right'   => 'uid',
                'level'            => ACCESS_READ);

        // form where clause
        $where = '';
        if (isset($args['letter'])) {
            $where = "WHERE pn_uname LIKE '".DataUtil::formatForStore($args['letter'])."%'";
        }

        $objArray = DBUtil::selectObjectArray('users', $where, 'uname', $startnum-1, $numitems, '', $permFilter);

        // Check for a DB error
        if ($objArray === false) {
            return LogUtil::registerError($this->__('Error! Could not load data.'));
        }

        return $objArray;
    }

    /**
     * Get a specific user record.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['uid']   (numeric) The id of user to get (required, unless uname specified).
     *                    $args['uname'] (string)  The user name of user to get (ignored if uid is specified, otherwise required).
     *
     * @return array The user record as an array, or false on failure.
     */
    public function get($args)
    {
        // Argument check
        if (!isset($args['uid']) || !is_numeric($args['uid'])) {
            if (!isset($args['uname'])) {
                return LogUtil::registerArgsError();
            }
        }

        $pntable = System::dbGetTables();
        $userscolumn = $pntable['users_column'];

        // calculate the where statement
        if (isset($args['uid'])) {
            $where = "$userscolumn[uid]='" . DataUtil::formatForStore($args['uid']) . "'";
        } else {
            $where = "$userscolumn[uname]='" . DataUtil::formatForStore($args['uname']) . "'";
        }

        $obj = DBUtil::selectObject('users', $where);

        // Security check
        if ($obj && !SecurityUtil::checkPermission('Users::', "$obj[uname]::$obj[uid]", ACCESS_READ)) {
            return false;
        }

        // Return the item array
        return $obj;
    }

    /**
     * Count and return the number of users.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['letter'] (string) If specified, then only those user records whose user name begins with the specified letter are counted.
     *
     * @todo Shouldn't there be some sort of limit on the select/loop??
     *
     * @return int Number of users.
     */
    public function countItems($args)
    {
        // form where clause
        $where = '';
        if (isset($args['letter'])) {
            $where = "WHERE pn_uname LIKE '".DataUtil::formatForStore($args['letter'])."%'";
        }

        return DBUtil::selectObjectCount('users', $where);
    }

    /**
     * Get user properties.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['proplabel'] (string) If specified only the value of the specified property (label) is returned.
     *
     * @return array An array of user properties, or false on failure.
     */
    public function optionalItems($args)
    {
        $items = array();

        if (!SecurityUtil::checkPermission('Users::', '::', ACCESS_READ)) {
            return $items;
        }

        if (!ModUtil::available('Profile') || !ModUtil::dbInfoLoad('Profile')) {
            return false;
        }

        $pntable = System::dbGetTables();
        $propertycolumn = $pntable['user_property_column'];

        $extrawhere = '';
        if (isset($args['proplabel']) && !empty($args['proplabel'])) {
            $extrawhere = "AND $propertycolumn[prop_label] = '".DataUtil::formatForStore($args['proplabel'])."'";
        }

        $where = "WHERE  $propertycolumn[prop_weight] != 0
                  AND    $propertycolumn[prop_dtype] != '-1' $extrawhere";

        $orderby = "ORDER BY $propertycolumn[prop_weight]";

        $objArray = DBUtil::selectObjectArray('user_property', $where, $orderby);

        if ($objArray === false) {
            LogUtil::registerError($this->__('Error! Could not load data.'));
            return $objArray;
        }

        $ak = array_keys($objArray);
        foreach ($ak as $v) {
            $prop_validation = @unserialize($objArray[$v]['prop_validation']);
            $prop_array = array('prop_viewby'      => $prop_validation['viewby'],
                    'prop_displaytype' => $prop_validation['displaytype'],
                    'prop_listoptions' => $prop_validation['listoptions'],
                    'prop_note'        => $prop_validation['note'],
                    'prop_validation'  => $prop_validation['validation']);

            array_push($objArray[$v], $prop_array);
        }

        return $objArray;
    }

    /**
     * Validate new user information entered by the user.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['uname']        (string) The proposed user name for the new user record.
     *                    $args['email']        (string) The proposed e-mail address for the new user record.
     *                    $args['password_reminder'] (string) The proposed password reminder entered by the user.
     *                    $args['agreetoterms'] (int)    A flag indicating that the user has agreed to the site's terms and policies; 0 indicates no, otherwise yes.
     *
     * @return array An array containing an error code and a result message. Possible error codes are:
     *               -1=NoPermission 1=EverythingOK 2=NotaValidatedEmailAddr
     *               3=NotAgreeToTerms 4=InValidatedUserName 5=UserNameTooLong
     *               6=UserNameReserved 7=UserNameIncludeSpace 8=UserNameTaken
     *               9=EmailTaken 11=User Agent Banned 12=Email Domain banned 18=no password reminder
     *
     */
    public function checkUser($args)
    {
        if (!SecurityUtil::checkPermission('Users::', '::', ACCESS_READ)) {
            return -1;
        }

        if (!System::varValidate($args['email'], 'email')) {
            return 2;
        }

        if (ModUtil::available('legal')) {
            if ($args['agreetoterms'] == 0) {
                return 3;
            }
        }

        if ((!$args['uname']) || !(!preg_match("/[[:space:]]/", $args['uname'])) || !System::varValidate($args['uname'], 'uname')) {
            return 4;
        }

        if (strlen($args['uname']) > 25) {
            return 5;
        }

        // admins are allowed to add any usernames, even those defined as being illegal
        if (!SecurityUtil::checkPermission('Users::', '::', ACCESS_ADMIN)) {
            // check for illegal usernames
            $reg_illegalusername = ModUtil::getVar('Users', 'reg_Illegalusername');
            if (!empty($reg_illegalusername)) {
                $usernames = explode(" ", $reg_illegalusername);
                $count = count($usernames);
                $pregcondition = "/((";
                for ($i = 0; $i < $count; $i++) {
                    if ($i != $count-1) {
                        $pregcondition .= $usernames[$i] . ")|(";
                    } else {
                        $pregcondition .= $usernames[$i] . "))/iAD";
                    }
                }
                if (preg_match($pregcondition, $args['uname'])) {
                    return 6;
                }
            }
        }

        if (strrpos($args['uname'], ' ') > 0) {
            return 7;
        }

        // check existing and active user
        $ucount = DBUtil::selectObjectCountByID('users', $args['uname'], 'uname', 'lower');
        if ($ucount) {
            return 8;
        }

        // check pending user
        $ucount = DBUtil::selectObjectCountByID('users_temp', $args['uname'], 'uname', 'lower');
        if ($ucount) {
            return 8;
        }

        if (ModUtil::getVar('Users', 'reg_uniemail')) {
            $ucount = DBUtil::selectObjectCountByID('users', $args['email'], 'email');
            if ($ucount) {
                return 9;
            }
        }

        if (ModUtil::getVar('Users', 'moderation')) {
            $ucount = DBUtil::selectObjectCountByID('users_temp', $args['uname'], 'uname');
            if ($ucount) {
                return 8;
            }

            $ucount = DBUtil::selectObjectCountByID('users_temp', $args['email'], 'email');
            if (ModUtil::getVar('Users', 'reg_uniemail')) {
                if ($ucount) {
                    return 9;
                }
            }
        }

        $emailVerification = ModUtil::getVar('Users', 'reg_verifyemail');
        if (!$emailVerification || $emailVerification == UserUtil::VERIFY_USERPWD) {
            if (!isset($args['password_reminder']) || empty($args['password_reminder'])) {
                return 18;
            }
        }
        // else z_exit?? because it really should be set at this point, either by the user, or some system-generated value.

        $useragent = strtolower(System::serverGetVar('HTTP_USER_AGENT'));
        $illegaluseragents = ModUtil::getVar('Users', 'reg_Illegaluseragents');
        if (!empty($illegaluseragents)) {
            $disallowed_useragents = str_replace(', ', ',', $illegaluseragents);
            $checkdisallowed_useragents = explode(',', $disallowed_useragents);
            $count = count($checkdisallowed_useragents);
            $pregcondition = "/((";
            for ($i = 0; $i < $count; $i++) {
                if ($i != $count-1) {
                    $pregcondition .= $checkdisallowed_useragents[$i] . ")|(";
                } else {
                    $pregcondition .= $checkdisallowed_useragents[$i] . "))/iAD";
                }
            }
            if (preg_match($pregcondition, $useragent)) {
                return 11;
            }
        }

        $illegaldomains = ModUtil::getVar('Users', 'reg_Illegaldomains');
        if (!empty($illegaldomains)) {
            list($foo, $maildomain) = explode('@', $args['email']);
            $maildomain = strtolower($maildomain);
            $disallowed_domains = str_replace(', ', ',', $illegaldomains);
            $checkdisallowed_domains = explode(',', $disallowed_domains);
            if (in_array($maildomain, $checkdisallowed_domains)) {
                return 12;
            }
        }

        return 1;
    }

    /**
     * Complete the process of creating a new user or new user registration from a registration request form.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['isadmin']           (bool)   Whether the new user record is being submitted by a user with admin permissions or not.
     *                    $args['user_regdate']      (string) An SQL date-time to override the registration date and time.
     *                    $args['user_viewmail']     (int)    Whether the user has selected to allows his e-mail address to be viewed or not.
     *                    $args['storynum']          (int)    The number of News module stories to show on the main page.
     *                    $args['commentlimit']      (int)    The limit on the size of this user's comments.
     *                    $args['timezoneoffset']    (int)    The user's time zone offset.
     *                    $args['usermustconfirm']   (int)    Whether the user must activate his account or not.
     *                    $args['skipnotifications'] (bool)   Whether e-mail notifications should be skipped or not.
     *                    $args['moderated']         (bool)   If true, then this record is being added as a result of an admin approval of a pending registration.
     *                    $args['hash_method']       (int)    A code indicated what hash method was used to store the user's encrypted password in the users_temp table.
     *                    $args['uname']             (string) The user name to store on the new user record.
     *                    $args['email']             (string) The e-mail address to store on the new user record.
     *                    $args['pass']              (string) The new password to store on the new user record.
     *                    $args['dynadata']          (array)  An array of data to be stored by the designated profile module and associated with this user record.
     *
     * @return bool True on success, otherwise false.
     */
    public function finishNewUser($args)
    {
        if (!SecurityUtil::checkPermission('Users::', '::', ACCESS_READ)) {
            return false;
        }

        // arguments defaults
        if (!isset($args['isadmin'])) {
            $args['isadmin'] = false;
        }
        if (!isset($args['user_regdate'])) {
            $args['user_regdate'] = DateUtil::getDatetime();
        }
        if (!isset($args['user_viewemail'])) {
            $args['user_viewemail'] = '0';
        }
        if (!isset($args['storynum'])) {
            $args['storynum'] = '5';
        }
        if (!isset($args['commentlimit'])) {
            $args['commentlimit'] = '4096';
        }
        if (!isset($args['timezoneoffset'])) {
            $args['timezoneoffset'] = System::getVar('timezone_offset');
        }
        if (!isset($args['usermustconfirm'])) {
            $args['usermustconfirm'] = 0;
        }
        // allows to run without email notifications
        if (!isset($args['skipnotifications'])) {
            $args['skipnotifications'] = false;
        }

        // hash methods array
        $hashMethodsArray = ModUtil::apiFunc('Users', 'user', 'getHashMethods', array('reverse' => false));

        // make password
        $hashAlgorithmName = ModUtil::getVar('Users', 'hash_method');
        $hashMethodKey = $hashMethodsArray[$hashAlgorithmName];

        if (isset($args['moderated']) && $args['moderated'] == true) {
            $makepass  = $args['pass'];
            $cryptpass = $args['pass'];
            $hashMethodKey = $args['hash_method'];
            $passwordReminder = $args['__ATTRIBUTES__']['password_reminder'];
            $activated = UserUtil::ACTIVATED_ACTIVE;
        } else {
            if ((ModUtil::getVar('Users', 'reg_verifyemail') == UserUtil::VERIFY_SYSTEMPWD) && !$args['isadmin']) {
                $makepass = $this->makePass();
                $cryptpass = hash($hashAlgorithmName, $makepass);
                $passwordReminder = $this->__('(Password generated by site)');
                $activated = UserUtil::ACTIVATED_ACTIVE;
            } elseif (ModUtil::getVar('Users', 'reg_verifyemail') == UserUtil::VERIFY_USERPWD) {
                $makepass = $args['pass'];
                $cryptpass = hash($hashAlgorithmName, $args['pass']);
                if ($args['isadmin']) {
                    $passwordReminder = $this->__('(Password provided by site administrator)');
                } else {
                    $passwordReminder = $args['password_reminder'];
                }
                $activated = ($args['isadmin'] && isset($args['usermustconfirm']) && ($args['usermustconfirm'] != 1))
                    ? UserUtil::ACTIVATED_ACTIVE
                    : UserUtil::ACTIVATED_INACTIVE;
            } else {
                $makepass = $args['pass']; // for welcome email. [class007]
                $cryptpass = hash($hashAlgorithmName, $args['pass']);
                if ($args['isadmin']) {
                    $passwordReminder = $this->__('(Password provided by site administrator)');
                } else {
                    $passwordReminder = $this->__('(Password generated by site)');
                }
                $activated = UserUtil::ACTIVATED_ACTIVE;
            }
        }

        if (isset($args['moderated']) && $args['moderated']) {
            $moderation = false;
        } elseif (!$args['isadmin']) {
            $moderation = ModUtil::getVar('Users', 'moderation');
            $args['moderated'] = false;
        } else {
            $moderation = false;
        }

        $pntable = System::dbGetTables();

        // We keep dynata as is if moderation is on as all dynadata will go in one field
        if ($moderation) {
            $column     = $pntable['users_temp_column'];
            $columnid   = $column['tid'];
        } else {
            $column     = $pntable['users_column'];
            $columnid   = $column['uid'];
        }

        $sitename  = System::getVar('sitename');
        $siteurl   = System::getBaseUrl();

        // create output object
        $pnRender = Renderer::getInstance('Users', false);
        $pnRender->assign('sitename', $sitename);
        $pnRender->assign('siteurl', substr($siteurl, 0, strlen($siteurl)-1));

        $obj = array();
        // do moderation stuff and exit
        if ($moderation) {
            $dynadata = isset($args['dynadata']) ? $args['dynadata'] : FormUtil::getPassedValue('dynadata', array());

            $obj['uname']        = $args['uname'];
            $obj['email']        = $args['email'];
            $obj['pass']         = $cryptpass;
            $obj['dynamics']     = @serialize($dynadata);
            $obj['comment']      = ''; //$args['comment'];
            $obj['type']         = 1;
            $obj['tag']          = 0;
            $obj['hash_method']  = $hashMethodKey;
            $obj['__ATTRIBUTES__']['password_reminder'] = $passwordReminder;

            $obj = DBUtil::insertObject($obj, 'users_temp', 'tid');

            if (!$obj) {
                return false;
            }
            if (!$args['skipnotifications']) {
                $pnRender->assign('email', $args['email']);
                $pnRender->assign('uname', $args['uname']);
                //$pnRender->assign('uid', $args['uid']);
                $pnRender->assign('makepass', $makepass);
                $pnRender->assign('moderation', $moderation);
                $pnRender->assign('moderated', $args['moderated']);

                // Password Email - Must be send now as the password will be encrypted and unretrievable later on.
                $message = $pnRender->fetch('users_userapi_welcomeemail.htm');

                $subject = $this->__f('Password for %1$s from %2$s', array($args['uname'], $sitename));
                ModUtil::apiFunc('Mailer', 'user', 'sendMessage', array('toaddress' => $args['email'], 'subject' => $subject, 'body' => $message, 'html' => true));

                // mail notify email to inform admin about registration
                if (ModUtil::getVar('Users', 'reg_notifyemail') != '' && $moderation == 1) {
                    $email2 = ModUtil::getVar('Users', 'reg_notifyemail');
                    $subject2 = $this->__('New user account registered');
                    $message2 = $pnRender->fetch('users_userapi_adminnotificationmail.htm');
                    ModUtil::apiFunc('Mailer', 'user', 'sendMessage', array('toaddress' => $email2, 'subject' => $subject2, 'body' => $message2, 'html' => true));
                }
            }
            return $obj['tid'];
        }

        $obj['uname']           = $args['uname'];
        $obj['email']           = $args['email'];
        $obj['user_regdate']    = $args['user_regdate'];
        $obj['user_viewemail']  = $args['user_viewemail'];
        $obj['user_theme']      = '';
        $obj['pass']            = $cryptpass;
        $obj['storynum']        = $args['storynum'];
        $obj['ublockon']        = 0;
        $obj['ublock']          = '';
        $obj['theme']           = '';
        $obj['counter']         = 0;
        $obj['activated']       = $activated;
        $obj['hash_method']     = $hashMethodKey;
        $obj['__ATTRIBUTES__']['password_reminder'] = $passwordReminder;

        $profileModule = System::getVar('profilemodule', '');
        $useProfileModule = (!empty($profileModule) && ModUtil::available($profileModule));

        // call the profile manager to handle dyndata if needed
        if ($useProfileModule) {
            $adddata = ModUtil::apiFunc($profileModule, 'user', 'insertDyndata', $args);
            if (is_array($adddata)) {
                $obj = array_merge($adddata, $obj);
            }
        }

        $res = DBUtil::insertObject($obj, 'users', 'uid');

        if (!$res) {
            return false;
        }

        $uid = $obj['uid'];

        // Add user to group
        // TODO - move this to a groups API calls
        $gid = ModUtil::getVar('Groups', 'defaultgroup');
        $group = DBUtil::selectObjectByID('groups', $gid, 'gid');
        if (!$group) {
            return false;
        }

        $obj = array();
        $obj['gid'] = $group['gid'];
        $obj['uid'] = $uid;
        $res = DBUtil::insertObject($obj, 'group_membership', 'dummy');
        if (!$res) {
            return false;
        }

        if (!$args['skipnotifications']) {
            $from = System::getVar('adminmail');

            // begin mail user
            $pnRender->assign('email', $args['email']);
            $pnRender->assign('uname', $args['uname']);
            $pnRender->assign('uid', $uid);
            $pnRender->assign('makepass', $makepass);
            $pnRender->assign('moderated', $args['moderated']);
            $pnRender->assign('moderation', $moderation);
            $pnRender->assign('user_regdate', $args['user_regdate']);

            if ($activated == UserUtil::ACTIVATED_ACTIVE) {
                // Password Email & Welcome Email
                $message = $pnRender->fetch('users_userapi_welcomeemail.htm');
                $subject = $this->__f('Password for %1$s from %2$s', array($args['uname'], $sitename));
                ModUtil::apiFunc('Mailer', 'user', 'sendMessage', array('toaddress' => $args['email'], 'subject' => $subject, 'body' => $message, 'html' => true));

            } else {
                // Activation Email
                $subject = $this->__f('Activation of %s', $args['uname']);
                // add en encoded activation code. The string is split with a hash (this character isn't used by base 64 encoding)
                $pnRender->assign('code', base64_encode($uid . '#' . $args['user_regdate']));
                $message = $pnRender->fetch('users_userapi_activationemail.htm');
                ModUtil::apiFunc('Mailer', 'user', 'sendMessage', array('toaddress' => $args['email'], 'subject' => $subject, 'body' => $message, 'html' => true));
            }

            // mail notify email to inform admin about activation
            if (ModUtil::getVar('Users', 'reg_notifyemail') != '') {
                $email2 = ModUtil::getVar('Users', 'reg_notifyemail');
                $subject2 = $this->__('New user account activated');
                $message2 = $pnRender->fetch('users_userapi_adminnotificationemail.htm');
                ModUtil::apiFunc('Mailer', 'user', 'sendMessage', array('toaddress' => $email2, 'subject' => $subject2, 'body' => $message2, 'html' => true));
            }
        }
        // Let other modules know we have created an item
        ModUtil::callHooks('item', 'create', $uid, array('module' => 'Users'));

        return $uid;
    }

    /**
     * Send the user a lost user name code.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['idfield'] (string) The value 'email'.
     *                    $args['id'] (string) The user's e-mail address.
     *
     * @return bool True if user name sent; otherwise false.
     */
    public function mailUname($args)
    {
        $emailMessageSent = false;

        if (!isset($args['id']) || empty($args['id']) || !isset($args['idfield']) || empty($args['idfield'])
            || (($args['idfield'] != 'email') && ($args['idfield'] != 'uid')))
        {
            return LogUtil::registerArgsError();
        }

        $adminRequested = (isset($args['adminRequest']) && is_bool($args['adminRequest']) && $args['adminRequest']);

        $user = UserUtil::getVars($args['id'], true, $args['idfield']);

        if (!$user) {
            LogUtil::registerError('Sorry! Could not find any matching user account.');
        } else {
            $renderer = Renderer::getInstance('Users', false);
            $renderer->assign('uname', $user['uname']);
            $renderer->assign('sitename', System::getVar('sitename'));
            $renderer->assign('hostname', System::serverGetVar('REMOTE_ADDR'));
            $renderer->assign('url',  ModUtil::url('Users', 'user', 'loginScreen', array(), null, null, true));
            $renderer->assign('adminRequested',  $adminRequested);
            $htmlBody = $renderer->fetch('users_userapi_lostunamemail.htm');
            $plainTextBody = $renderer->fetch('users_userapi_lostunamemail.txt');

            $subject = $this->__f('User name for %s', $user['uname']);

            $emailMessageSent = ModUtil::apiFunc('Mailer', 'user', 'sendMessage',
                array(
                    'toaddress' => $user['email'],
                    'subject'   => $subject,
                    'body'      => $htmlBody,
                    'altbody'   => $plainTextBody
                ));
            if (!$emailMessageSent) {
                LogUtil::registerError($this->__('Error! Unable to send user name e-mail message.'));
            }
        }

        return $emailMessageSent;
    }

    /**
     * Send the user a lost password confirmation code.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['uname'] (string) The user's user name.
     *                    $args['email'] (string) The user's e-mail address.
     *
     * @return bool True if confirmation code sent; otherwise false.
     */
    public function mailConfirmationCode($args)
    {
        $emailMessageSent = false;

        if (!isset($args['id']) || empty($args['id']) || !isset($args['idfield']) || empty($args['idfield'])
            || (($args['idfield'] != 'uname') && ($args['idfield'] != 'email') && ($args['idfield'] != 'uid')))
        {
            return LogUtil::registerArgsError();
        }

        $adminRequested = (isset($args['adminRequest']) && is_bool($args['adminRequest']) && $args['adminRequest']);

        $user = UserUtil::getVars($args['id'], true, $args['idfield']);

        if (!$user) {
            LogUtil::registerError('Sorry! Could not find any matching user account.');
        } else {
            $hashMethodsArray = ModUtil::apiFunc('Users', 'user', 'getHashMethods', array('reverse' => false));
            $hashAlgorithmName = ModUtil::getVar('Users', 'hash_method');
            $hashMethodKey = $hashMethodsArray[$hashAlgorithmName];

            $confirmationCode = RandomUtil::getString(5, 5, false, false, true, false, true, false, false, array('O','0','o','i','j','I','l','1','!','|'));
            $confirmationCodeHash = SecurityUtil::getSaltedHash($hashAlgorithmName, $confirmationCode, 5, '$');

            if ($confirmationCodeHash !== false) {
                $userShadowObj = DBUtil::selectObjectByID('users_shadow', $user['uid'], 'uid');

                if (!$userShadowObj) {
                    $userShadowObj = array();
                    $userShadowObj['uid'] = $user['uid'];
                    $userShadowObj['code'] = $confirmationCodeHash;
                    $userShadowObj['code_hash_method'] = $hashMethodKey;
                    $userShadowObj['code_expires'] = 0;
                    $codeSaved = DBUtil::insertObject($userShadowObj, 'users_shadow');
                } else {
                    $userShadowObj['code'] = $confirmationCodeHash;
                    $userShadowObj['code_hash_method'] = $hashMethodKey;
                    $userShadowObj['code_expires'] = 0;
                    $codeSaved = DBUtil::updateObject($userShadowObj, 'users_shadow');
                }

                if ($codeSaved) {
                    $urlArgs = array();
                    $urlArgs['code'] = urlencode($confirmationCode);
                    $urlArgs[$args['idfield']] = urlencode($args['id']);

                    $renderer = Renderer::getInstance('Users', false);
                    $renderer->assign('uname', $user['uname']);
                    $renderer->assign('sitename', System::getVar('sitename'));
                    $renderer->assign('hostname', System::serverGetVar('REMOTE_ADDR'));
                    $renderer->assign('code', $confirmationCode);
                    $renderer->assign('url',  ModUtil::url('Users', 'user', 'lostPasswordCode', $urlArgs, null, null, true));
                    $renderer->assign('adminRequested',  $adminRequested);
                    $htmlBody = $renderer->fetch('users_userapi_lostpasscodemail.htm');
                    $plainTextBody = $renderer->fetch('users_userapi_lostpasscodemail.txt');

                    $subject = $this->__f('Confirmation code for %s', $user['uname']);

                    $emailMessageSent = ModUtil::apiFunc('Mailer', 'user', 'sendMessage',
                        array(
                            'toaddress' => $user['email'],
                            'subject'   => $subject,
                            'body'      => $htmlBody,
                            'altbody'   => $plainTextBody
                        ));
                    if (!$emailMessageSent) {
                        LogUtil::registerError($this->__('Error! Unable to send confirmation code e-mail message.'));
                    }
                } else {
                    LogUtil::registerError($this->__('Error! Unable to save confirmation code.'));
                }
            } else {
                LogUtil::registerError($this->__("Error! Unable to create confirmation code."));
            }
        }

        return $emailMessageSent;
    }

    /**
     * Check a lost password confirmation code.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['idfield'] (string) Either 'uname' or 'email'.
     *                    $args['id'] (string) The user's user name or e-mail address, depending on the value of idfield.
     *                    $args['code']  (string) The confirmation code.
     *
     * @return bool True if the new password was sent; otherwise false.
     */
    public function checkConfirmationCode($args)
    {
        $codeIsGood = false;

        if (!isset($args['id']) || empty($args['id']) || !isset($args['idfield']) || empty($args['idfield'])
            || !isset($args['code']) || empty($args['code'])
            || (($args['idfield'] != 'uname') && ($args['idfield'] != 'email')))
        {
            return LogUtil::registerArgsError();
        }

        $user = UserUtil::getVars($args['id'], true, $args['idfield']);

        if (!$user) {
            LogUtil::registerError('Sorry! Could not find any matching user account.');
        } else {
            $userShadowObj = DBUtil::selectObjectByID('users_shadow', $user['uid'], 'uid');

            if ($userShadowObj) {
                $hashMethodsArray = ModUtil::apiFunc('Users', 'user', 'getHashMethods', array('reverse' => true));
                $hashAlgorithmName = $hashMethodsArray[$userShadowObj['code_hash_method']];

                $codeIsGood = SecurityUtil::checkSaltedHash($hashAlgorithmName, $args['code'], $userShadowObj['code'], '$');

                if ($codeIsGood) {
                    // Guard against insanely bad time() by ensuring at least 1
                    $timestamp = max(time(), 1);

                    if (($userShadowObj['code_expires'] != 0) && ($timestamp > $userShadowObj['code_expires'])) {
                        $codeIsGood = false;
                        LogUtil::registerError('Sorry! your confirmation code has expired.');
                    } else {
                        // Prevent code reuse.
                        $userShadowObj['code_expires'] = -1;
                        DBUtil::updateObject($userShadowObj, 'users_shadow');
                    }
                }
            } else {
                LogUtil::registerError('Sorry! Could not retrieve a confirmation code for that account.');
            }
        }

        return $codeIsGood;
    }

    /**
     * Send the user a lost password.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['uname'] (string) The user's user name.
     *                    $args['email'] (string) The user's e-mail address.
     *                    $args['code']  (string) The confirmation code.
     *
     * @return bool True if the new password was sent; otherwise false.
     */
    public function mailPassword($args)
    {
        $emailMessageSent = false;
        $passwordSaved = false;

        if (!isset($args['id']) || empty($args['id']) || !isset($args['idfield']) || empty($args['idfield'])
            || !isset($args['code']) || empty($args['code'])
            || (($args['idfield'] != 'uname') && ($args['idfield'] != 'email')))
        {
            return LogUtil::registerArgsError();
        }

        $user = UserUtil::getVars($args['id'], true, $args['idfield']);

        if (!$user) {
            LogUtil::registerError('Sorry! Could not find any matching user account.');
        } elseif (ModUtil::apiFunc('Users', 'user', 'checkConfirmationCode', array(
                'idfield' => $args['idfield'],
                'id' => $args['id'],
                'code' => $args['code'],
            )))
        {
            $newpass = $this->makePass();
            $passwordReminder = $this->__('(Site-generated password)');

            $renderer = Renderer::getInstance('Users', false);
            $renderer->assign('uname', $user['uname']);
            $renderer->assign('sitename', System::getVar('sitename'));
            $renderer->assign('hostname', System::serverGetVar('REMOTE_ADDR'));
            $renderer->assign('password', $newpass);
            $renderer->assign('recovery_forcepwdchg', ModUtil::getVar('Users', 'recovery_forcepwdchg', false));
            $renderer->assign('url',  ModUtil::url('Users', 'user', 'loginScreen', array(), null, null, true));
            $htmlBody = $renderer->fetch('users_userapi_passwordmail.htm');
            $plainTextBody = $renderer->fetch('users_userapi_passwordmail.txt');

            $subject = $this->__f('Password for %s', $user['uname']);

            $emailMessageSent = ModUtil::apiFunc('Mailer', 'user', 'sendMessage',
                array(
                    'toaddress' => $user['email'],
                    'subject'   => $subject,
                    'body'      => $htmlBody,
                    'altbody'   => $plainTextBody
                ));

            if ($emailMessageSent) {
                // Next step: add the new password to the database
                // Note: cannot use UserUtil::setPassword() because there is no user logged in!
                $hashAlgorithmName = ModUtil::getVar('Users', 'hash_method');
                $hashMethodsArray = ModUtil::apiFunc('Users', 'user', 'getHashMethods', array('reverse' => false));
                $hashMethodKey = $hashMethodsArray[$hashAlgorithmName];
                $cryptPass = hash($hashAlgorithmName, $newpass);

                $forceChange = ModUtil::getVar('Users', 'recovery_forcepwdchg', false);

                $obj = array();
                $obj['uid'] = $user['uid'];
                $obj['pass']  = $cryptPass;
                $obj['hash_method'] = $hashMethodKey;
                $obj['__ATTRIBUTES__']['password_reminder'] = $passwordReminder;
                if ($forceChange) {
                    $obj['activated'] = 4;
                }
                $passwordSaved = DBUtil::updateObject ($obj, 'users', '', 'uid');

                if (!$passwordSaved) {
                    LogUtil::registerError($this->__('Error! Unable to save new password.'));
                }
            } else {
                LogUtil::registerError($this->__('Error! Unable to send new password e-mail message.'));
            }
        }

        return ($emailMessageSent && $passwordSaved);
    }

    /**
     * Activate a user's account.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['regdate'] (string)  An SQL date-time containing the user's original registration date-time.
     *                    $args['uid']     (numeric) The id of the user account to activate.
     *
     * @return bool True on success, otherwise false.
     */
    public function activateUser($args)
    {
        if (!SecurityUtil::checkPermission('Users::', '::', ACCESS_READ)) {
            return false;
        }

        // Preventing reactivation from same link !
        $newregdate = DateUtil::getDatetime(strtotime($args['regdate'])+1);
        $obj = array('uid'          => $args['uid'],
                'activated'    => UserUtil::ACTIVATED_ACTIVE,
                'user_regdate' => DataUtil::formatForStore($newregdate));

        ModUtil::callHooks('item', 'update', $args['uid'], array('module' => 'Users'));

        return (boolean)DBUtil::updateObject($obj, 'users', '', 'uid');
    }

    /**
     * Display a message indicating that the user's session has expired.
     *
     * @return string The rendered template.
     */
    public function expiredSession()
    {
        $pnRender = Renderer::getInstance('Users', false);
        return $pnRender->fetch('users_userapi_expiredsession.htm');
    }

    /**
     * Generate a password for the user.
     *
     * @return string The generated password.
     */
    private function makePass()
    {
        $minpass = (int)ModUtil::getVar('Users', 'minpass', 5);
        return RandomUtil::getString($minpass, 8, false, false, true, false, true, false, true, array('0', 'o', 'l', '1'));
    }

    /**
     * Retrieve an array of hash method codes indexed by hash method name, or an array of hash method names indexed by hash method codes.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['reverse'] (bool) If false, then an array of codes index by name; if true, then an array of names indexed by code.
     *
     * @return array The array of hash method codes and names.
     */
    public function getHashMethods($args)
    {
        $reverse = isset($args['reverse']) ? $args['reverse'] : false;

        if ($reverse) {

            return array(1 => 'md5',
                    5 => 'sha1',
                    8 => 'sha256');
        } else {

            return array('md5'    => 1,
                    'sha1'   => 5,
                    'sha256' => 8);
        }
    }

    /**
     * Retrieve the account links for each user module.
     *
     * @return array An array of links for the user account page.
     */
    public function accountLinks()
    {
        // Get all user modules
        $mods = ModUtil::getAllMods();

        if ($mods == false) {
            return false;
        }

        $accountlinks = array();

        foreach ($mods as $mod) {
            // saves 17 system checks
            if ($mod['type'] == 3 && !in_array($mod['name'], array('Admin', 'Categories', 'Groups', 'Theme', 'Users'))) {
                continue;
            }

            $modpath = ($mod['type'] == ModUtil::TYPE_SYSTEM) ? 'system' : 'modules';

            $ooAccountApiFile = DataUtil::formatForOS("{$modpath}/{$mod['directory']}/lib/{$mod['directory']}/Api/Account.php");
            $legacyAccountApiFile = DataUtil::formatForOS("{$modpath}/{$mod['directory']}/pnaccountapi.php");
            if (file_exists($ooAccountApiFile) || file_exists($legacyAccountApiFile)) {
                $items = ModUtil::apiFunc($mod['name'], 'account', 'getAll');
                if ($items) {
                    foreach ($items as $k => $item) {
                        // check every retured link for permissions
                        if (SecurityUtil::checkPermission('Users::', "$mod[name]::$item[title]", ACCESS_READ)) {
                            if (!isset($item['module'])) {
                                $item['module']  = $mod['name'];
                            }
                            // insert the indexed item
                            $accountlinks["$mod[name]{$k}"] = $item;
                        }
                    }
                }
            } else {
                $items = false;
            }
        }

        return $accountlinks;
    }

    /**
     * Save the preliminary user e-mail until user's confirmation.
     *
     * @param array $args All parameters passed to this function.
     *                    $args['newemail'] (string) The new e-mail address to store pending confirmation.
     *
     * @return bool True if success and false otherwise.
     */
    public function savePreEmail($args)
    {
        if (!UserUtil::isLoggedIn()) {
            return LogUtil::registerPermissionError();
        }

        $pntable = System::dbGetTables();
        $column = $pntable['users_temp_column'];

        // delete all the records from e-mail confirmation that have more than five days
        $fiveDaysAgo =  time() - 5*24*60*60;
        $where = "$column[dynamics]<" . $fiveDaysAgo . " AND $column[type]=2";
        DBUtil::deleteWhere ('users_temp', $where);

        $uname = UserUtil::getVar('uname');

        // generate a randomize value of 7 characters needed to confirm the e-mail change
        $confirmValue = substr(md5(time() . rand(0, 30000)),0 ,7);;

        $obj = array('uname' => $uname,
                'email' => DataUtil::formatForStore($args['newemail']),
                'pass' => '',
                'dynamics' => time(),
                'comment' => $confirmValue,
                'type' => 2,
                'tag' => 0);

        // checks if user has request the change recently and it is not confirmed
        $exists = DBUtil::selectObjectCountByID('users_temp', $uname, 'uname', 'lower');

        if (!$exists) {
            // create a new insert
            $obj = DBUtil::insertObject($obj, 'users_temp', 'tid');
        } else {
            $where = "$column[uname]='" . $uname . "' AND $column[type]=2";
            // update the current insert
            $obj = DBUtil::updateObject($obj, 'users_temp', $where);
        }

        if (!$obj) {
            return false;
        }

        // send confirmation e-mail to user with the changing code
        $subject = $this->__f('Confirmation change of e-mail for %s', $uname);

        $pnRender = Renderer::getInstance('Users', false);
        $pnRender->assign('uname', $uname);
        $pnRender->assign('email', UserUtil::getVar('email'));
        $pnRender->assign('newemail', $args['newemail']);
        $pnRender->assign('sitename', System::getVar('sitename'));
        $pnRender->assign('url',  ModUtil::url('Users', 'user', 'confirmChEmail', array('confirmcode' => $confirmValue), null, null, true));

        $message = $pnRender->fetch('users_userapi_confirmchemail.htm');
        $sent = ModUtil::apiFunc('Mailer', 'user', 'sendMessage', array('toaddress' => $args['newemail'], 'subject' => $subject, 'body' => $message, 'html' => true));

        if (!$sent) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve the user's new e-mail address that is awaiting his confirmation.
     *
     * @return string The e-mail address waiting for confirmation for the current user.
     */
    public function getUserPreEmail()
    {
        if (!UserUtil::isLoggedIn()) {
            return LogUtil::registerPermissionError();
        }
        $item = DBUtil::selectObjectById('users_temp', UserUtil::getVar('uname'), 'uname');
        if (!$item) {
            return false;
        }
        return $item;
    }

    /**
     * Get available user menu links.
     *
     * @return array An array of menu links.
     */
    public function getLinks()
    {

        $allowregistration = ModUtil::getVar('Users', 'reg_allowreg');

        $links = array();

        if (SecurityUtil::checkPermission('Users::', '::', ACCESS_READ)) {
            $links[] = array('url' => ModUtil::url('Users', 'user', 'loginScreen'), 'text' => $this->__('Log in'), 'class' => 'z-icon-es-user');
            $links[] = array('url' => ModUtil::url('Users', 'user', 'lostPwdUname'), 'text' => $this->__('Lost user name or password'), 'class' => 'z-icon-es-password');
        }

        if ($allowregistration) {
            $links[] = array('url' => ModUtil::url('Users', 'user', 'register'), 'text' => $this->__('New account'), 'class' => 'z-icon-es-adduser');
        }

        return $links;
    }
}
