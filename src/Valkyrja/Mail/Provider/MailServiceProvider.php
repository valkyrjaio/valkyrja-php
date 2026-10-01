<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Mail\Provider;

use GuzzleHttp\Client;
use Mailgun\HttpClient\HttpClientConfigurator;
use Mailgun\Mailgun;
use Override;
use PHPMailer\PHPMailer\PHPMailer as PHPMailerClient;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Mail\Data\Contract\MailConfigContract;
use Valkyrja\Mail\Data\Contract\MailLogConfigContract;
use Valkyrja\Mail\Data\Contract\MailMailgunConfigContract;
use Valkyrja\Mail\Data\Contract\MailPhpMailerConfigContract;
use Valkyrja\Mail\Data\MailConfig;
use Valkyrja\Mail\Data\MailLogConfig;
use Valkyrja\Mail\Data\MailMailgunConfig;
use Valkyrja\Mail\Data\MailPhpMailerConfig;
use Valkyrja\Mail\Mailer\Contract\MailerContract;
use Valkyrja\Mail\Mailer\LogMailer;
use Valkyrja\Mail\Mailer\MailgunMailer;
use Valkyrja\Mail\Mailer\NullMailer;
use Valkyrja\Mail\Mailer\PhpMailer;

class MailServiceProvider implements ServiceProviderContract
{
    /**
     * Publish the mail config service.
     */
    public static function publishConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof MailConfigContract) {
            $container->setSingleton(MailConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            MailConfigContract::class,
            new MailConfig()
        );
    }

    /**
     * Publish the mailgun mailer config service.
     */
    public static function publishMailgunConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof MailMailgunConfigContract) {
            $container->setSingleton(MailMailgunConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            MailMailgunConfigContract::class,
            new MailMailgunConfig()
        );
    }

    /**
     * Publish the PHPMailer mailer config service.
     */
    public static function publishPhpMailerConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof MailPhpMailerConfigContract) {
            $container->setSingleton(MailPhpMailerConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            MailPhpMailerConfigContract::class,
            new MailPhpMailerConfig()
        );
    }

    /**
     * Publish the log mailer config service.
     */
    public static function publishLogConfig(ContainerContract $container): void
    {
        $config = $container->getSingleton(ConfigContract::class);

        if ($config instanceof MailLogConfigContract) {
            $container->setSingleton(MailLogConfigContract::class, $config);

            return;
        }

        $container->setSingleton(
            MailLogConfigContract::class,
            new MailLogConfig()
        );
    }

    /**
     * Publish the mailer service.
     */
    public static function publishMailer(ContainerContract $container): void
    {
        $config = $container->getSingleton(MailConfigContract::class);

        $container->setSingleton(
            MailerContract::class,
            $container->getSingleton($config->defaultMailer),
        );
    }

    /**
     * Publish the mailgun mailer service.
     */
    public static function publishMailgunMailer(ContainerContract $container): void
    {
        $config = $container->getSingleton(MailMailgunConfigContract::class);

        $container->setSingleton(
            MailgunMailer::class,
            new MailgunMailer(
                $container->getSingleton(Mailgun::class),
                $config->mailgunDomain
            )
        );
    }

    /**
     * Publish the mailgun service.
     */
    public static function publishMailgun(ContainerContract $container): void
    {
        $container->setSingleton(
            Mailgun::class,
            new Mailgun(
                $container->getSingleton(HttpClientConfigurator::class),
            )
        );
    }

    /**
     * Publish the mailgun service.
     */
    public static function publishMailgunHttpClientConfigurator(ContainerContract $container): void
    {
        $config = $container->getSingleton(MailMailgunConfigContract::class);

        $httpClientConfigurator = new HttpClientConfigurator();

        $container->setSingleton(
            HttpClientConfigurator::class,
            $httpClientConfigurator
                ->setApiKey($config->mailgunApiKey)
                ->setHttpClient(new Client())
        );
    }

    /**
     * Publish the PHPMailer mailer service.
     */
    public static function publishPhpMailer(ContainerContract $container): void
    {
        $container->setSingleton(
            PhpMailer::class,
            new PhpMailer(
                $container->getSingleton(PHPMailerClient::class),
            ),
        );
    }

    /**
     * Publish the PHPMailer client service.
     */
    public static function publishPhpMailerClient(ContainerContract $container): void
    {
        $app    = $container->getSingleton(ApplicationContract::class);
        $config = $container->getSingleton(MailPhpMailerConfigContract::class);

        $host       = $config->phpMailerHost;
        $port       = $config->phpMailerPort;
        $username   = $config->phpMailerUsername;
        $password   = $config->phpMailerPassword;
        $encryption = $config->phpMailerEncryption;

        // Create a new instance of the PHPMailer class
        $mailer = new PHPMailerClient(true);

        // Enable verbose debug output
        $mailer->SMTPDebug = $app->getDebugMode() ? 2 : 0;
        // Set mailer to use SMTP
        $mailer->isSMTP();
        // Specify main and backup SMTP servers
        $mailer->Host = $host;
        // SMTP Port
        $mailer->Port = $port;
        // Enable SMTP authentication
        $mailer->SMTPAuth = true;
        // SMTP username
        $mailer->Username = $username;
        // SMTP password
        $mailer->Password = $password;
        // Enable TLS encryption, `ssl` also accepted
        $mailer->SMTPSecure = $encryption;

        $container->setSingleton(
            PHPMailerClient::class,
            $mailer
        );
    }

    /**
     * Publish the log mailer service.
     */
    public static function publishLogMailer(ContainerContract $container): void
    {
        $config = $container->getSingleton(MailLogConfigContract::class);

        $container->setSingleton(
            LogMailer::class,
            new LogMailer(
                $container->getSingleton($config->mailLogLogger),
            ),
        );
    }

    /**
     * Publish the null mailer service.
     */
    public static function publishNullMailer(ContainerContract $container): void
    {
        $container->setSingleton(
            NullMailer::class,
            new NullMailer(),
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publishers(): array
    {
        return [
            MailConfigContract::class          => [self::class, 'publishConfig'],
            MailMailgunConfigContract::class   => [self::class, 'publishMailgunConfig'],
            MailPhpMailerConfigContract::class => [self::class, 'publishPhpMailerConfig'],
            MailLogConfigContract::class       => [self::class, 'publishLogConfig'],
            MailerContract::class              => [self::class, 'publishMailer'],
            MailgunMailer::class               => [self::class, 'publishMailgunMailer'],
            Mailgun::class                     => [self::class, 'publishMailgun'],
            HttpClientConfigurator::class      => [self::class, 'publishMailgunHttpClientConfigurator'],
            PhpMailer::class                   => [self::class, 'publishPhpMailer'],
            PHPMailerClient::class             => [self::class, 'publishPhpMailerClient'],
            LogMailer::class                   => [self::class, 'publishLogMailer'],
            NullMailer::class                  => [self::class, 'publishNullMailer'],
        ];
    }
}
