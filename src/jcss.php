<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2 (or at your option, any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

ini_set('mbstring.internal_encoding', 'UTF-8');
ini_set('default_charset', 'UTF-8');
define('ACCESS_ADMIN', 1);
include 'config/config.php';
include 'lib/util/FormUtil.php';
global $ZConfig;
$f = FormUtil::getPassedValue('f', null, 'GET');

if (!isset($f)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// clean $f
$f = preg_replace('`/`', '', $f);

// set full path to the file
$f = $ZConfig['System']['temp'] . '/Theme_cache/' . $f;

if (!is_readable($f)) {
    header('HTTP/1.0 404 Not Found');
    die('ERROR: Requested file not readable.');
}

// child lock
$signingKey = md5($ZConfig['DBInfo']['default']['dsn']);

$contents = file_get_contents($f);
if (!is_serialized($contents)) {
    header('HTTP/1.0 404 Not Found');
    die('ERROR: Corrupted file.');
}

$dataArray = unserialize($contents);
if (!isset($dataArray['contents']) || !isset($dataArray['ctype']) || !isset($dataArray['lifetime']) || !isset($dataArray['gz']) || !isset($dataArray['signature'])) {
    header('HTTP/1.0 404 Not Found');
    die('ERROR: Invalid data.');
}

// check signature
if (md5($dataArray['contents'] . $dataArray['ctype'] . $dataArray['lifetime'] . $dataArray['gz'] . $signingKey) != $dataArray['signature']) {
    header('HTTP/1.0 404 Not Found');
    die('ERROR: File has been altered.');
}

// gz handlers if requested
if ($dataArray['gz']) {
    ini_set('zlib.output_handler', '');
    ini_set('zlib.output_compression', 1);
}

header("Content-type: $dataArray[ctype]");
header('Cache-Control: must-revalidate');
header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $dataArray['lifetime']) . ' GMT');
echo $dataArray['contents'];
exit;

function is_serialized($string)
{
    return ($string == 'b:0;' ? true : (bool) @unserialize($string));
}

function pnStripslashes(&$value)
{
    if (empty($value))
        return;

    if (!is_array($value)) {
        $value = stripslashes($value);
    } else {
        array_walk($value, 'pnStripslashes');
    }
}

class SecurityUtil
{
    function checkPermission()
    {
        return true;
    }
}

