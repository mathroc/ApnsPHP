<?php

declare(strict_types=1);

namespace Tests;

use ApnsPHP;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testPayloadBasic(): void
    {
        $message = new ApnsPHP\Message();
        $message->setText('Text');
        $message->setTopic('ch.protonmail.test');

        $messagePayload = (fn () => $this->getPayloadDictionary())->call($message);

        $payload = [
            'aps' => [
                'alert' => 'Text',
            ],
        ];

        Assert::assertEquals($payload, $messagePayload);
    }

    public function testPayload(): void
    {
        $message = new ApnsPHP\Message();
        $message->setTitle('title');
        $message->setText('Foo');
        $message->setBadge(3);
        $message->setContentAvailable(true);
        $message->setCustomProperty('foo', 'bar');
        $message->setTopic('ch.protonmail.test');
        $message->setPushType('background');

        $messagePayload = (fn () => $this->getPayloadDictionary())->call($message);

        $payload = [
            'aps' => [
                'alert' => [
                    'title' => 'title',
                    'body'  => 'Foo'
                ],
                'badge' => 3,
                'content-available' => 1,
            ],
            'foo' => 'bar',
        ];

        Assert::assertEquals($payload, $messagePayload);
    }

    public function testJsonPayloadBasic(): void
    {
        $message = new ApnsPHP\Message();
        $message->setText('Text');
        $message->setTopic('ch.protonmail.test');

        Assert::assertSame('{"aps":{"alert":"Text"}}', $message->getPayload());
        Assert::assertSame('{"aps":{"alert":"Text"}}', (string) $message);
    }

    public function testJsonEmptyPayload(): void
    {
        $message = new ApnsPHP\Message();

        Assert::assertSame('{"aps":{}}', $message->getPayload());
        Assert::assertSame('{"aps":{}}', (string) $message);
    }

    public function testJsonPayloadWithSpecialChar(): void
    {
        $message = new ApnsPHP\Message();
        $message->setText('Text ⚛️');

        Assert::assertSame('{"aps":{"alert":"Text ⚛️"}}', $message->getPayload());
        Assert::assertSame('{"aps":{"alert":"Text ⚛️"}}', (string) $message);
    }

    public function testJsonLongPayloadAndAutoAdjust(): void
    {
        $message = new ApnsPHP\Message();
        $message->setText(str_repeat('1234567890', 100));

        Assert::assertSame(
            '{"aps":{"alert":"' . str_repeat('1234567890', 100) . '"}}',
            $message->getPayload(),
        );

        $message = new ApnsPHP\Message();
        $message->setText(str_repeat('1234567890', 500));

        Assert::assertNotSame(
            '{"aps":{"alert":"' . str_repeat('1234567890', 500) . '"}}',
            $message->getPayload(),
        );

        Assert::assertSame(4095, strlen($message->getPayload()));
    }
}
