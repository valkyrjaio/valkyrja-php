<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Session\Manager;

use Override;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Valkyrja\Http\Message\Enum\SameSite;
use Valkyrja\Session\Data\SessionPhpConfig;
use Valkyrja\Session\Manager\Abstract\Session;
use Valkyrja\Session\Manager\Contract\SessionContract;
use Valkyrja\Session\Manager\PhpSession;
use Valkyrja\Session\Throwable\Exception\SessionIdFailureException;
use Valkyrja\Session\Throwable\Exception\SessionInvalidSessionIdException;
use Valkyrja\Session\Throwable\Exception\SessionNameFailureException;
use Valkyrja\Session\Throwable\Exception\SessionStartFailureException;
use Valkyrja\Tests\Fixtures\Session\PhpSessionWithAlreadyActiveFixture;
use Valkyrja\Tests\Fixtures\Session\PhpSessionWithFailingGetIdFixture;
use Valkyrja\Tests\Fixtures\Session\PhpSessionWithFailingGetNameFixture;
use Valkyrja\Tests\Fixtures\Session\PhpSessionWithFailingStartFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function session_destroy;
use function session_status;
use function str_repeat;

use const PHP_SESSION_ACTIVE;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhpSessionTest extends TestCase
{
    protected SessionPhpConfig $cookieConfig;

    protected function setUp(): void
    {
        $this->cookieConfig = new SessionPhpConfig(
            phpCookiePath: '/',
            phpCookieDomain: null,
            phpCookieLifetime: 0,
            phpCookieSecure: false,
            phpCookieHttpOnly: false,
            phpCookieSameSite: SameSite::NONE,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testImplementsSessionContract(): void
    {
        $session = new PhpSession($this->cookieConfig);

        self::assertInstanceOf(SessionContract::class, $session);
    }

    public function testExtendsSession(): void
    {
        $session = new PhpSession($this->cookieConfig);

        self::assertInstanceOf(Session::class, $session);
    }

    public function testDoesNotStartTwice(): void
    {
        $session = new PhpSessionWithAlreadyActiveFixture($this->cookieConfig);

        self::assertSame(1, $session->sessionStartCount);

        $session->start();

        self::assertSame(1, $session->sessionStartCount);
    }

    public function testStartWithNonNullDomain(): void
    {
        $cookieConfig = new SessionPhpConfig(
            phpCookiePath: '/',
            phpCookieDomain: 'example.com',
            phpCookieLifetime: 0,
            phpCookieSecure: false,
            phpCookieHttpOnly: false,
            phpCookieSameSite: SameSite::NONE,
        );

        $session = new PhpSession($cookieConfig);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testConstructorWithSessionIdAndName(): void
    {
        $session = new PhpSession($this->cookieConfig, 'test-session-id', 'MY_SESSION');

        self::assertNotSame('test-session-id', $session->getId());
        self::assertSame('MY_SESSION', $session->getName());
    }

    public function testSetStoresValue(): void
    {
        $session = new PhpSession($this->cookieConfig);
        $session->set('key', 'value');

        self::assertSame('value', $session->get('key'));
    }

    public function testGetReturnsDefaultForNonExistent(): void
    {
        $session = new PhpSession($this->cookieConfig);

        self::assertSame('default', $session->get('nonexistent', 'default'));
    }

    public function testHasReturnsTrueForExistingItem(): void
    {
        $session = new PhpSession($this->cookieConfig);
        $session->set('key', 'value');

        self::assertTrue($session->has('key'));
    }

    public function testHasReturnsFalseForNonExistentItem(): void
    {
        $session = new PhpSession($this->cookieConfig);

        self::assertFalse($session->has('nonexistent'));
    }

    public function testRemoveReturnsTrueAndRemovesItem(): void
    {
        $session = new PhpSession($this->cookieConfig);
        $session->set('key', 'value');

        self::assertTrue($session->remove('key'));
        self::assertFalse($session->has('key'));
    }

    public function testRemoveReturnsFalseForNonExistent(): void
    {
        $session = new PhpSession($this->cookieConfig);

        self::assertFalse($session->remove('nonexistent'));
    }

    public function testAllReturnsAllData(): void
    {
        $session = new PhpSession($this->cookieConfig);
        $session->set('key1', 'value1');
        $session->set('key2', 'value2');

        self::assertSame(['key1' => 'value1', 'key2' => 'value2'], $session->all());
    }

    public function testClearRemovesAllData(): void
    {
        $session = new PhpSession($this->cookieConfig);
        $session->set('key1', 'value1');
        $session->set('key2', 'value2');

        $session->clear();

        self::assertSame([], $session->all());
    }

    public function testDestroyRemovesAllData(): void
    {
        $session = new PhpSession($this->cookieConfig);
        $session->set('key1', 'value1');

        $session->destroy();

        self::assertSame([], $session->all());
    }

    public function testStartThrowsSessionStartFailureOnFailure(): void
    {
        $this->expectException(SessionStartFailureException::class);
        $this->expectExceptionMessage('The session failed to start');

        new PhpSessionWithFailingStartFixture($this->cookieConfig);
    }

    public function testGetIdThrowsSessionIdFailureOnFailure(): void
    {
        $session = new PhpSessionWithFailingGetIdFixture($this->cookieConfig);

        $this->expectException(SessionIdFailureException::class);
        $this->expectExceptionMessage('Retrieval of session id failed');

        $session->getId();
    }

    public function testGetNameThrowsSessionNameFailureOnFailure(): void
    {
        $session = new PhpSessionWithFailingGetNameFixture($this->cookieConfig);

        $this->expectException(SessionNameFailureException::class);
        $this->expectExceptionMessage('Retrieval of session id failed');

        $session->getName();
    }

    public function testSetIdThrowsInvalidSessionIdForInvalidId(): void
    {
        $session = new PhpSession($this->cookieConfig);

        $this->expectException(SessionInvalidSessionIdException::class);
        $this->expectExceptionMessage("The session id, 'invalid id with spaces!', is invalid!");

        $session->setId('invalid id with spaces!');
    }

    public function testConstructorThrowsInvalidSessionIdForInvalidId(): void
    {
        $this->expectException(SessionInvalidSessionIdException::class);
        $this->expectExceptionMessage("The session id, 'invalid@id#chars', is invalid!");

        new PhpSession($this->cookieConfig, 'invalid@id#chars');
    }

    public function testSetIdThrowsInvalidSessionIdForTooLongId(): void
    {
        $session = new PhpSession($this->cookieConfig);

        $this->expectException(SessionInvalidSessionIdException::class);

        $session->setId(str_repeat('a', 129));
    }

    public function testSetIdThrowsInvalidSessionIdForEmptyId(): void
    {
        $session = new PhpSession($this->cookieConfig);

        $this->expectException(SessionInvalidSessionIdException::class);

        $session->setId('');
    }
}
