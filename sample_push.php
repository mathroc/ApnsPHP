<?php

declare(strict_types=1);

/**
 * @file
 * sample_push.php
 *
 * Push demo
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

// Adjust to your timezone
date_default_timezone_set('Europe/Rome');

// Report all PHP errors
error_reporting(-1);

// Using Composer autoload all classes are loaded on-demand
require_once 'vendor/autoload.php';

// Instantiate a new ApnsPHP_Push object
$push = new \ApnsPHP\Push(
    \ApnsPHP\SharedConfig::ENVIRONMENT_SANDBOX,
    'server_certificates_bundle_sandbox.pem',
    \ApnsPHP\SharedConfig::PROTOCOL_HTTP,
);

// Set the Provider Certificate passphrase
// $push->setProviderCertificatePassphrase('test');

// Set the Root Certificate Autority to verify the Apple remote peer
$push->setRootCertificationAuthority('entrust_root_certification_authority.pem');

// Connect to the Apple Push Notification Service
$push->connect();

// Instantiate a new Message with a single recipient
$message = new \ApnsPHP\Message('1e82db91c7ceddd72bf33d74ae052ac9c84a065b35148ac401388843106a7485');

// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
// over a ApnsPHP_Message object retrieved with the getErrors() message.
$message->setCustomIdentifier('7530A828-E58E-433E-A38F-D8042208CF96');

// Set badge icon to "3"
$message->setBadge(3);

// Set a simple welcome text
$message->setText('Hello APNs-enabled device!');

// Play the default sound
$message->setSound();

// Set a custom property
$message->setCustomProperty('acme2', ['bang', 'whiz']);

// Set another custom property
$message->setCustomProperty('acme3', ['bing', 'bong']);

// Set the expiry value to 30 seconds
$message->setExpiry(10);

$message->setCollapseId('42424242');

// Add the message to the message queue
$push->add($message);

// Send all messages in the message queue
$push->send();

// Disconnect from the Apple Push Notification Service
$push->disconnect();

// Examine the error message container
$aErrorQueue = $push->getErrors();
if (!empty($aErrorQueue)) {
	var_dump($aErrorQueue);
}
