<img src="http://immobiliare.github.io/ApnsPHP/images/logo.png" width="48"> ApnsPHP: Apple Push Notification & Feedback Provider
==========================

<p align="center">
	<img src="https://poser.pugx.org/duccio/apns-php/downloads">
	<img src="https://poser.pugx.org/duccio/apns-php/d/monthly">
	<img src="https://poser.pugx.org/duccio/apns-php/d/daily">
	<img src="https://poser.pugx.org/duccio/apns-php/license">
</p>

A **full set** of *open source* PHP classes to interact with the **Apple Push Notification service** for the iPhone, iPad and the iPod Touch.

Based on https://github.com/M2mobi/ApnsPHP and https://github.com/immobiliare/ApnsPHP

- [Sample PHP Push code](sample_push.php)
- [Sample PHP Feedback code](sample_feedback.php)
- [Sample PHP Server code](sample_server.php)
- [Full APIs Documentation](http://immobiliare.github.io/ApnsPHP/html/index.html)
- [How to generate a Push Notification certificate and download the Entrust Root Authority certificate](Doc/CertificateCreation.md)

Packagist
-------

https://packagist.org/packages/protonlabs/apns-php

```
composer req protonlabs/apns-php
```

Architecture
-------

- **Message class**, to build a notification payload.
- **Push class**, to push one or more messages to Apple Push Notification service.
- **Feedback class**, to query the Apple Feedback service to get the list of broken device tokens.
- **Push Server class**, to create a Push Server with one or more (forked) processes reading from a common message queue.
- **Log class/interface**, to log to standard output or for custom logging purpose (supports loggers implementing the PSR-3 logger interface).

Details
---------

In the Apple Push Notification Binary protocol there isn't a real-time feedback about the correctness of notifications pushed to the server. So, after each write to the server, the Push class waits for the "read stream" to change its status (or at least N microseconds); if it happened and the client socket receives an "end-of-file" from the server, the notification pushed to the server was broken, the Apple server has closed the connection and the client needs to reconnect to send other notifications still on the message queue.

To speed-up the sending activities the Push Server class can be used to create a Push Notification Server with many processes that reads a common message queue and sends parallel Push Notifications.

All client-server activities are based on the "on error, retry" pattern with customizable timeouts, retry times and retry intervals.

Requirements
-------------

PHP 7.4.0 or later with OpenSSL, PCNTL, System V shared memory and semaphore support.
