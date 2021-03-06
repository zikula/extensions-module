<?php

declare(strict_types=1);

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula - https://ziku.la/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\ExtensionsModule\Event;

/**
 * Occurs when a module has been successfully installed but before the Cache has been reloaded.
 */
class ExtensionPostInstallEvent extends ExtensionStateEvent
{
}
