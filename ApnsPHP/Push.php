<?php

/**
 * @file
 * Push class definition.
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://code.google.com/p/apns-php/wiki/License
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to aldo.armiento@gmail.com so we can send you a copy immediately.
 *
 * @author (C) 2010 Aldo Armiento (aldo.armiento@gmail.com)
 * @version $Id$
 */

namespace ApnsPHP;

use ApnsPHP\Push\Exception;

/**
 * The Push Notification Provider.
 *
 * The class manages a message queue and sends notifications payload to Apple Push
 * Notification Service.
 */
class Push extends SharedConfig
{
    /** @var int Payload command. */
    protected const COMMAND_PUSH = 1;

    /** @var int Error-response packet size. */
    protected const ERROR_RESPONSE_SIZE = 6;

    /** @var int Error-response command code. */
    protected const ERROR_RESPONSE_COMMAND = 8;

    /** @var int Status code for internal error (not Apple). */
    protected const STATUS_CODE_INTERNAL_ERROR = 999;

    /**
     * @var array<int, string> Error-response messages.
     * @deprecated
     */
    protected $errorResponseMessages = [
        0   => 'No errors encountered',
        1   => 'Processing error',
        2   => 'Missing device token',
        3   => 'Missing topic',
        4   => 'Missing payload',
        5   => 'Invalid token size',
        6   => 'Invalid topic size',
        7   => 'Invalid payload size',
        8   => 'Invalid token',
        self::STATUS_CODE_INTERNAL_ERROR => 'Internal error'
    ];

    /** @var array<int, string> HTTP/2 Error-response messages. */
    protected $HTTPErrorResponseMessages = [
        200 => 'Success',
        400 => 'Bad request',
        403 => 'There was an error with the certificate',
        405 => 'The request used a bad :method value. Only POST requests are supported',
        410 => 'The device token is no longer active for the topic',
        413 => 'The notification payload was too large',
        429 => 'The server received too many requests for the same device token',
        500 => 'Internal server error',
        503 => 'The server is shutting down and unavailable',
        self::STATUS_CODE_INTERNAL_ERROR => 'Internal error'
    ];

    /** @var int Send retry times. */
    protected $sendRetryTimes = 3;

    /** @var string[] Service URLs environments. */
    protected $serviceURLs = [
        'tls://gateway.push.apple.com:2195', // Production environment
        'tls://gateway.sandbox.push.apple.com:2195' // Sandbox environment
    ];

    /** @var string[] HTTP/2 Service URLs environments. */
    protected $HTTPServiceURLs = [
        'https://api.push.apple.com:443', // Production environment
        'https://api.development.push.apple.com:443' // Sandbox environment
    ];

    /** @var array<int, array> Message queue. */
    protected $messageQueue = [];

    /** @var array<int, array> Error container. */
    protected $errors = [];

    /**
     * Set the send retry times value.
     *
     * If the client is unable to send a payload to to the server retries at least
     * for this value. The default send retry times is 3.
     *
     * @param int $retryTimes Send retry times.
     */
    public function setSendRetryTimes($retryTimes)
    {
        $this->sendRetryTimes = (int)$retryTimes;
    }

    /**
     * Get the send retry time value.
     *
     * @return int Send retry times.
     */
    public function getSendRetryTimes()
    {
        return $this->sendRetryTimes;
    }

    /**
     * Adds a message to the message queue.
     */
    public function add(Message $message)
    {
        $messagePayload = $message->getPayload();
        $recipients = $message->getRecipientsNumber();

        $messageQueueLen = count($this->messageQueue);
        for ($i = 0; $i < $recipients; $i++) {
            $messageId = $messageQueueLen + $i + 1;
            $messages = [
                'MESSAGE' => $message->selfForRecipient($i),
                'ERRORS' => []
            ];
            if ($this->protocol === self::PROTOCOL_BINARY) {
                $messages['BINARY_NOTIFICATION'] = $this->getBinaryNotification(
                    $message->getRecipient($i),
                    $messagePayload,
                    $messageId,
                    $message->getExpiry()
                );
            }
            $this->messageQueue[$messageId] = $messages;
        }
    }

    /**
     * Sends all messages in the message queue to Apple Push Notification Service.
     */
    public function send()
    {
        if (!$this->hSocket) {
            throw new Exception(
                'Not connected to Push Notification Service'
            );
        }

        if (empty($this->messageQueue)) {
            throw new Exception(
                'No notifications queued to be sent'
            );
        }

        $this->errors = [];
        $run = 1;
        while (($messageAmount = count($this->messageQueue)) > 0) {
            $this->logger()->info("Sending messages queue, run #{$run}: $messageAmount message(s) left in queue.");

            $error = false;
            foreach ($this->messageQueue as $key => &$messages) {
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                $message = $messages['MESSAGE'];
                $customIdentifier = (string)$message->getCustomIdentifier();
                $customIdentifier = sprintf(
                    '[custom identifier: %s]',
                    empty($customIdentifier) ? 'unset' : $customIdentifier
                );

                $errorAmount = 0;
                if (!empty($messages['ERRORS'])) {
                    foreach ($messages['ERRORS'] as $errors) {
                        if ($errors['statusCode'] == 0) {
                            $this->logger()->info(
                                "Message ID {$key} {$customIdentifier} has no error ({$errors['statusCode']}),
                                 removing from queue..."
                            );
                            $this->removeMessageFromQueue($key);
                            continue 2;
                        } elseif ($errors['statusCode'] > 1 && $errors['statusCode'] <= 8) {
                            $this->logger()->warning(
                                "Message ID {$key} {$customIdentifier} has an unrecoverable error
                                 ({$errors['statusCode']}), removing from queue without retrying..."
                            );
                            $this->removeMessageFromQueue($key, true);
                            continue 2;
                        }
                    }
                    if (($errorAmount = count($messages['ERRORS'])) >= $this->sendRetryTimes) {
                        $this->logger()->warning(
                            "Message ID {$key} {$customIdentifier} has {$errorAmount} errors, removing from queue..."
                        );
                        $this->removeMessageFromQueue($key, true);
                        continue;
                    }
                }

                $messageBytes = strlen($this->protocol === self::PROTOCOL_HTTP ? $message->getPayload() :
                                       $messages['BINARY_NOTIFICATION']);
                $this->logger()->debug("Sending message ID {$key} {$customIdentifier} (" . ($errorAmount + 1) .
                                        "/{$this->sendRetryTimes}): {$messageBytes} bytes.");

                $errorMessage = null;

                if ($this->protocol === self::PROTOCOL_HTTP) {
                    if (!$this->httpSend($message, $reply)) {
                        $errorMessage = [
                            'identifier' => $key,
                            'statusCode' => curl_getinfo($this->hSocket, CURLINFO_HTTP_CODE),
                            'statusMessage' => $reply
                        ];
                    }
                } else {
                    if ($messageBytes !== ($written = (int)@fwrite($this->hSocket, $messages['BINARY_NOTIFICATION']))) {
                        $errorMessage = [
                            'identifier' => $key,
                            'statusCode' => self::STATUS_CODE_INTERNAL_ERROR,
                            'statusMessage' => sprintf(
                                '%s (%d bytes written instead of %d bytes)',
                                $this->errorResponseMessages[self::STATUS_CODE_INTERNAL_ERROR],
                                $written,
                                $messageBytes
                            )
                        ];
                    }
                }
                usleep($this->writeInterval);

                $error = $this->updateQueue($errorMessage);
                if ($error) {
                    break;
                }
            }

            if (!$error) {
                if ($this->protocol === self::PROTOCOL_BINARY) {
                    $read = [$this->hSocket];
                    $null = null;
                    $changedStreams = @stream_select(
                        $read,
                        $null,
                        $null,
                        0,
                        $this->socketSelectTimeout
                    );
                    if ($changedStreams === false) {
                        $this->logger()->error('Unable to wait for a stream availability.');
                        break;
                    } elseif ($changedStreams > 0) {
                        $error = $this->updateQueue();
                        if (!$error) {
                            $this->messageQueue = [];
                        }
                    } else {
                        $this->messageQueue = [];
                    }
                } else {
                    $this->messageQueue = [];
                }
            }

            $run++;
        }
    }

    /**
     * @return string[]
     */
    private function getHttpHeaders(Message $message): array
    {
        $headers = ['Content-Type: application/json'];
        if (!empty($message->getTopic())) {
            $headers[] = sprintf('apns-topic: %s', $message->getTopic());
        }
        if (!empty($message->getExpiry())) {
            $headers[] = sprintf('apns-expiration: %s', $message->getExpiry());
        }
        if (!empty($message->getPriority())) {
            $headers[] = sprintf('apns-priority: %s', $message->getPriority());
        }
        if (!empty($message->getCollapseId())) {
            $headers[] = sprintf('apns-collapse-id: %s', $message->getCollapseId());
        }
        if (!empty($message->getCustomIdentifier())) {
            $headers[] = sprintf('apns-id: %s', $message->getCustomIdentifier());
        }
        if ($message->getPushType() !== null) {
            $headers[] = sprintf('apns-push-type: %s', $message->getPushType());
        }
        if (!empty($this->providerToken)) {
            $headers[] = sprintf('Authorization: Bearer %s', $this->providerToken);
        }

        return $headers;
    }

    /**
     * Send a message using the HTTP/2 API protocol.
     *
     * @param string $reply The reply message.
     * @return bool success of API call
     */
    private function httpSend(Message $message, &$reply)
    {
        $headers = $this->getHttpHeaders($message);

        if (
            !(curl_setopt_array($this->hSocket, [
                CURLOPT_POST => true,
                CURLOPT_URL => sprintf(
                    '%s/3/device/%s',
                    $this->HTTPServiceURLs[$this->environment],
                    $message->getRecipient()
                ),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $message->getPayload()
            ]) && ($reply = curl_exec($this->hSocket)) !== false)
        ) {
            return false;
        }

        return curl_getinfo($this->hSocket, CURLINFO_HTTP_CODE) === 200;
    }

    /**
     * Returns messages in the message queue.
     *
     * When a message is successful sent or reached the maximum retry time is removed
     * from the message queue and inserted in the Errors container. Use the getErrors()
     * method to retrive messages with delivery error(s).
     *
     * @param bool $empty @optional Empty message queue.
     * @return array Array of messages left on the queue.
     */
    public function getMessageQueue($empty = true)
    {
        $messages = $this->messageQueue;
        if ($empty) {
            $this->messageQueue = [];
        }
        return $messages;
    }

    /**
     * Returns messages not delivered to the end user because one (or more) error
     * occurred.
     *
     * @param bool $empty @optional Empty message container.
     * @return array Array of messages not delivered because one or more errors occurred.
     */
    public function getErrors($empty = true)
    {
        $messages = $this->errors;
        if ($empty) {
            $this->errors = [];
        }
        return $messages;
    }

    /**
     * Generate a binary notification from a device token and a JSON-encoded payload.
     *
     * @see http://tinyurl.com/ApplePushNotificationBinary
     *
     * @param string $deviceToken The device token.
     * @param string $payload The JSON-encoded payload.
     * @param int $messageId @optional Message unique ID.
     * @param int $expire @optional Seconds, starting from now, that
     *         identifies when the notification is no longer valid and can be discarded.
     *         Pass a negative value (-1 for example) to request that APNs not store
     *         the notification at all. Default is 86400 * 7, 7 days.
     * @return string A binary notification.
     * @deprecated
     */
    protected function getBinaryNotification($deviceToken, $payload, $messageId = 0, $expire = 604800)
    {
        $tokenLength = strlen($deviceToken);
        $payloadLength = strlen($payload);

        $notification  = pack(
            'CNNnH*',
            self::COMMAND_PUSH,
            $messageId,
            $expire > 0 ? time() + $expire : 0,
            self::DEVICE_BINARY_SIZE,
            $deviceToken
        );
        $notification .= pack('n', $payloadLength);
        $notification .= $payload;

        return $notification;
    }

    /**
     * Parses the error message.
     *
     * @param string $errorMessage The Error Message.
     * @return array Array with command, statusCode and identifier keys.
     * @deprecated
     */
    protected function parseErrorMessage($errorMessage)
    {
        return unpack('Ccommand/CstatusCode/Nidentifier', $errorMessage);
    }

    /**
     * Reads an error message (if present) from the main stream.
     * If the error message is present and valid the error message is returned,
     * otherwhise null is returned.
     *
     * @return array|null Return the error message array.
     */
    protected function readErrorMessage()
    {
        $errorMessage = @fread($this->hSocket, self::ERROR_RESPONSE_SIZE);
        if ($errorMessage === false || strlen($errorMessage) != self::ERROR_RESPONSE_SIZE) {
            return;
        }
        $errorResponse = $this->parseErrorMessage($errorMessage);
        if (!is_array($errorResponse) || empty($errorResponse)) {
            return;
        }
        if (!isset($errorResponse['command'], $errorResponse['statusCode'], $errorResponse['identifier'])) {
            return;
        }
        if ($errorResponse['command'] != self::ERROR_RESPONSE_COMMAND) {
            return;
        }
        $errorResponse['time'] = time();
        $errorResponse['statusMessage'] = 'None (unknown)';
        if (isset($this->errorResponseMessages[$errorResponse['statusCode']])) {
            $errorResponse['statusMessage'] = $this->errorResponseMessages[$errorResponse['statusCode']];
        }
        return $errorResponse;
    }

    /**
     * Checks for error message and deletes messages successfully sent from message queue.
     *
     * @param array $errorMessages @optional The error message. It will anyway
     *         always be read from the main stream. The latest successful message
     *         sent is the lowest between this error message and the message that
     *         was read from the main stream.
     *         @return bool True if an error was received.
     * @see readErrorMessage()
     */
    protected function updateQueue($errorMessages = null)
    {
        $streamErrorMessage = $this->readErrorMessage();
        if (!isset($errorMessages) && !isset($streamErrorMessage)) {
            return false;
        } elseif (isset($errorMessages, $streamErrorMessage)) {
            if ($streamErrorMessage['identifier'] <= $errorMessages['identifier']) {
                $errorMessages = $streamErrorMessage;
                unset($streamErrorMessage);
            }
        } elseif (!isset($errorMessages) && isset($streamErrorMessage)) {
            $errorMessages = $streamErrorMessage;
            unset($streamErrorMessage);
        }

        $this->logger()->error('Unable to send message ID ' .
            $errorMessages['identifier'] . ': ' .
            $errorMessages['statusMessage'] . ' (' . $errorMessages['statusCode'] . ').');

        $this->disconnect();

        foreach ($this->messageQueue as $key => &$message) {
            if ($key < $errorMessages['identifier']) {
                unset($this->messageQueue[$key]);
            } elseif ($key == $errorMessages['identifier']) {
                $message['ERRORS'][] = $errorMessages;
            } else {
                break;
            }
        }

        $this->connect();

        return true;
    }

    /**
     * Remove a message from the message queue.
     *
     * @param int $messageId The Message ID.
     * @param bool $error @optional Insert the message in the Error container.
     */
    protected function removeMessageFromQueue($messageId, $error = false)
    {
        if (!is_numeric($messageId) || $messageId <= 0) {
            throw new Exception(
                'Message ID format is not valid.'
            );
        }
        if (!isset($this->messageQueue[$messageId])) {
            throw new Exception(
                "The Message ID {$messageId} does not exists."
            );
        }
        if ($error) {
            $this->errors[$messageId] = $this->messageQueue[$messageId];
        }
        unset($this->messageQueue[$messageId]);
    }
}
