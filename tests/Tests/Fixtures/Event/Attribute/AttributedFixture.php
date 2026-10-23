<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Event\Attribute;

use Valkyrja\Event\Attribute\Listener;
use Valkyrja\Event\Attribute\Listener\Handler;
use Valkyrja\Tests\Unit\Event\Collector\AttributesListenerCollectorTest;

/**
 * Class with attributes used for unit testing.
 */
// Testing invalid attributes that have no method attached to them since this class has no constructor
#[Listener(AttributesListenerCollectorTest::VALUE1, 'AttributedClassValue1')]
#[Listener(AttributesListenerCollectorTest::VALUE2, 'AttributedClassValue2')]
final class AttributedFixture
{
    #[Listener(AttributesListenerCollectorTest::VALUE1, 'AttributedFixture::staticMethodValue1')]
    #[Listener(AttributesListenerCollectorTest::VALUE2, 'AttributedFixture::staticMethodValue2')]
    #[Handler([self::class, 'handler'])]
    public static function staticMethod(): string
    {
        return 'Static Method';
    }

    public static function handler(): string
    {
        return 'Handler';
    }

    public static function handler2(): string
    {
        return 'Handler2';
    }

    #[Listener(AttributesListenerCollectorTest::VALUE1, 'AttributedFixture->methodValue1', [self::class, 'handler2'])]
    #[Listener(AttributesListenerCollectorTest::VALUE2, 'AttributedFixture->methodValue2', [self::class, 'handler2'])]
    public function method(): string
    {
        return 'Method';
    }
}
