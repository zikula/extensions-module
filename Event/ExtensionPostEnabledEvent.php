<?php

declare(strict_types=1);

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula Foundation - https://ziku.la/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\ExtensionsModule\Event;

/**
 * Occurs when a module has been enabled after it was previously disabled.
 */
class ExtensionPostEnabledEvent extends ExtensionStateEvent
{
}