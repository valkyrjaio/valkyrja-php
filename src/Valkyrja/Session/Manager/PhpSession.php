<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Session\Manager;

use Override;
use Valkyrja\Session\Data\Contract\SessionPhpConfigContract;
use Valkyrja\Session\Manager\Abstract\Session;
use Valkyrja\Session\Throwable\Exception\SessionIdFailureException;
use Valkyrja\Session\Throwable\Exception\SessionInvalidSessionIdException;
use Valkyrja\Session\Throwable\Exception\SessionNameFailureException;
use Valkyrja\Session\Throwable\Exception\SessionStartFailureException;

use function headers_sent;
use function preg_match;
use function session_id;
use function session_name;
use function session_set_cookie_params;
use function session_start;
use function session_status;
use function session_unset;

use const PHP_SESSION_ACTIVE;

class PhpSession extends Session
{
    /**
     * @param non-empty-string|null $sessionId   The session id
     * @param non-empty-string|null $sessionName The session id
     */
    public function __construct(
        protected SessionPhpConfigContract $cookieConfig,
        string|null $sessionId = null,
        string|null $sessionName = null,
    ) {
        parent::__construct(
            sessionId: $sessionId,
            sessionName: $sessionName
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function start(): void
    {
        // If the session is already active
        if ($this->isActive() || $this->headersSent()) {
            // No need to reactivate
            return;
        }

        // Set the session cookie parameters
        session_set_cookie_params([
            'path'     => $this->cookieConfig->phpCookiePath,
            'domain'   => $this->cookieConfig->phpCookieDomain ?? '',
            'lifetime' => $this->cookieConfig->phpCookieLifetime,
            'secure'   => $this->cookieConfig->phpCookieSecure,
            'httponly' => $this->cookieConfig->phpCookieHttpOnly,
            'samesite' => $this->cookieConfig->phpCookieSameSite->value,
        ]);

        // If the session failed to start
        if (! $this->sessionStart()) {
            // Throw a new exception
            throw new SessionStartFailureException('The session failed to start');
        }

        // Set the data
        $this->data = &$_SESSION;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getId(): string
    {
        $sessionId = $this->sessionId();

        if ($sessionId === false) {
            throw new SessionIdFailureException('Retrieval of session id failed');
        }

        return $sessionId;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setId(string $id): void
    {
        parent::setId($id);

        $this->sessionId();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getName(): string
    {
        $sessionName = $this->sessionName();

        if ($sessionName === false) {
            throw new SessionNameFailureException('Retrieval of session id failed');
        }

        return $sessionName;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setName(string $name): void
    {
        parent::setName($name);

        $this->sessionName($name);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): void
    {
        parent::clear();

        session_unset();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function destroy(): void
    {
        parent::destroy();

        session_unset();
    }

    /**
     * Get or set the session id.
     */
    protected function sessionId(): string|false
    {
        return session_id();
    }

    /**
     * Get or set the session name.
     */
    protected function sessionName(string|null $name = null): string|false
    {
        return session_name($name);
    }

    /**
     * Start the session.
     */
    protected function sessionStart(): bool
    {
        return session_start();
    }

    /**
     * Determine if the headers have been sent.
     */
    protected function headersSent(): bool
    {
        return headers_sent();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function validateId(string $id): void
    {
        if (! preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $id)) {
            throw new SessionInvalidSessionIdException(
                "The session id, '$id', is invalid! "
                . 'Session id can only contain alpha numeric characters, dashes, commas, '
                . 'and be at least 1 character in length but up to 128 characters long.'
            );
        }
    }
}
