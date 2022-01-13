<?php

declare(strict_types=1);

namespace Tests;

use ApnsPHP;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class PushTest extends TestCase
{
    public function testWrongFile(): void
    {
        $this->expectExceptionMessageMatches('/Unable to read certificate file/');

        new ApnsPHP\Push(
            ApnsPHP\SharedConfig::ENVIRONMENT_SANDBOX,
            'wrong.pem',
            ApnsPHP\SharedConfig::PROTOCOL_HTTP,
        );
    }

    public function testConnectDisconnect(): void
    {
        $push = new ApnsPHP\Push(
            ApnsPHP\SharedConfig::ENVIRONMENT_SANDBOX,
            __DIR__ . '/key_test.p8',
            ApnsPHP\SharedConfig::PROTOCOL_HTTP,
        );

        $testLogger = self::createTestLogger();
        $push->setLogger($testLogger);
        $push->setTeamId('test-team');
        $push->connect();
        // $push->send();
        $push->disconnect();

        $concatMessages = implode("\n", $testLogger->messages);

        Assert::assertMatchesRegularExpression('/Initializing HTTP\/2/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Initialized HTTP\/2/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Disconnected/', $concatMessages);
    }

    public function testConnectTryToSendDisconnect(): void
    {
        $push = new ApnsPHP\Push(
            ApnsPHP\SharedConfig::ENVIRONMENT_SANDBOX,
            __DIR__ . '/key_test.p8',
            ApnsPHP\SharedConfig::PROTOCOL_HTTP,
        );

        $testLogger = self::createTestLogger();
        $push->setLogger($testLogger);
        $push->setTeamId('test-team');
        $push->connect();

        $message = new ApnsPHP\Message('aaaadb91c7ceddd72bf33d74ae052ac9c84a065b35148ac401388843106a0000');

        // Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
        // over a ApnsPHP_Message object retrieved with the getErrors() message.
        $message->setCustomIdentifier('7530A828-E58E-433E-A38F-D8042208CF96');

        // Set a simple welcome text
        $message->setText('Hello APNs-enabled device!');

        // Add the message to the message queue
        $push->add($message);

        $push->send();
        $push->disconnect();
        $push->disconnect(); // Let's do it twice, for test purpose

        $concatMessages = implode("\n", $testLogger->messages);

        Assert::assertCount(27, $testLogger->messages);

        Assert::assertMatchesRegularExpression('/Initializing HTTP\/2/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Initialized HTTP\/2/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Disconnected/', $concatMessages);

        Assert::assertMatchesRegularExpression('/Sending messages queue, run #1/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Sending messages queue, run #2/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Sending messages queue, run #3/', $concatMessages);
        Assert::assertMatchesRegularExpression('/Sending message ID 1/', $concatMessages);
        Assert::assertMatchesRegularExpression('/InvalidProviderToken/', $concatMessages);
        Assert::assertMatchesRegularExpression('/InvalidProviderToken/', $concatMessages);
    }

    private static function createTestLogger()
    {
        return new class () extends AbstractLogger
        {
            public array $messages = [];
            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
    }
}
