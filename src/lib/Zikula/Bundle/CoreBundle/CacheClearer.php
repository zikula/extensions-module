<?php
/**
 * Copyright Zikula Foundation 2014 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPv3 (or at your option any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Bundle\CoreBundle;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CacheClearer
{
    private $cacheDir;

    private $cachePrefix;
    
    private $cacheTypes;

    private $fs;

    public function __construct($cacheDir, $cachePrefix, $kernelContainerClass)
    {
        $this->cacheDir = $cacheDir;
        $this->cachePrefix = $cachePrefix;
        $this->fs = new Filesystem();

        $cacheFolder = $cacheDir . DIRECTORY_SEPARATOR;
        
        $this->cacheTypes = array(
            "symfony.routing.generator" => array(
                "$cacheFolder{$cachePrefix}UrlGenerator.php",
                "$cacheFolder{$cachePrefix}UrlGenerator.php.meta",
            ),
            "symfony.routing.matcher" => array(
                "$cacheFolder{$cachePrefix}UrlMatcher.php",
                "$cacheFolder{$cachePrefix}UrlMatcher.php.meta"
            ),
            "symfony.config" => array(
                "$cacheFolder$kernelContainerClass.php",
                "$cacheFolder$kernelContainerClass.php.meta",
                "$cacheFolder$kernelContainerClass.xml",
                "$cacheFolder{$kernelContainerClass}Compiler.log",
                "{$cacheFolder}classes.map"
            ),
        );
    }

    public function clear($type)
    {
        foreach ($this->cacheTypes as $cacheType => $files) {
            if (substr($cacheType, 0, strlen($type)) === $type) {
                $this->fs->remove($files);
            }
        }
    }
}
