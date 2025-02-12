<?php

/**
 * @file
 * CustomMessage class definition.
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

namespace ApnsPHP\Message;

use ApnsPHP\Message;

/**
 * The Push Notification CustomMessage Message.
 *
 * The class represents a custom message to be delivered to an end user device.
 * Please refer to Table 3-2 for more information.
 *
 * @see http://tinyurl.com/ApplePushNotificationPayload
 */
class CustomMessage extends Message
{
    /**< @type string The "View" button title. */
    protected $actionLocKey;

    /**< @type string A key to an alert-message string in a Localizable.strings file */
    protected $locKey;

    /**< @type array Variable string values to appear in place of the format specifiers in loc-key. */
    protected $locArgs;

    /**< @type string The filename of an image file in the application bundle. */
    protected $launchImage;

    /**< @type string The title of an short looknotification displayed on Apple Watch. */
    protected $title;

    /**< @type string The subtitle of a secondary description */
    protected $subTitle;

    /**
     * Set the "View" button title.
     *
     * If a string is specified, displays an alert with two buttons.
     * iOS uses the string as a key to get a localized string in the current localization
     * to use for the right button’s title instead of "View". If the value is an
     * empty string, the system displays an alert with a single OK button that simply
     * dismisses the alert when tapped.
     *
     * @param string $actionLocKey @optional The "View" button title, default
     *         empty string.
     */
    public function setActionLocKey($actionLocKey = '')
    {
        $this->actionLocKey = $actionLocKey;
    }

    /**
     * Get the "View" button title.
     *
     * @return string The "View" button title.
     */
    public function getActionLocKey()
    {
        return $this->actionLocKey;
    }

    /**
     * Set the alert-message string in Localizable.strings file for the current
     * localization (which is set by the user’s language preference).
     *
     * The key string can be formatted with %@ and %n$@ specifiers to take the variables
     * specified in loc-args.
     *
     * @param string $locKey The alert-message string.
     */
    public function setLocKey($locKey)
    {
        $this->locKey = $locKey;
    }

    /**
     * Get the alert-message string in Localizable.strings file.
     *
     * @return string The alert-message string.
     */
    public function getLocKey()
    {
        return $this->locKey;
    }

    /**
     * Set the variable string values to appear in place of the format specifiers
     * in loc-key.
     *
     * @param array $locArgs The variable string values.
     */
    public function setLocArgs($locArgs)
    {
        $this->locArgs = $locArgs;
    }

    /**
     * Get the variable string values to appear in place of the format specifiers
     * in loc-key.
     *
     * @return string The variable string values.
     */
    public function getLocArgs()
    {
        return $this->locArgs;
    }

    /**
     * Set the filename of an image file in the application bundle; it may include
     * the extension or omit it.
     *
     * The image is used as the launch image when users tap the action button or
     * move the action slider. If this property is not specified, the system either
     * uses the previous snapshot, uses the image identified by the UILaunchImageFile
     * key in the application’s Info.plist file, or falls back to Default.png.
     * This property was added in iOS 4.0.
     *
     * @param string $launchImage The filename of an image file.
     */
    public function setLaunchImage($launchImage)
    {
        $this->launchImage = $launchImage;
    }

    /**
     * Get the filename of an image file in the application bundle.
     *
     * @return string The filename of an image file.
     */
    public function getLaunchImage()
    {
        return $this->launchImage;
    }

    /**
     * Set the title of a short look Apple Watch notification.
     *
     * Currently only used when displaying notifications on Apple Watch.
     * See https://developer.apple.com/library/ios/documentation/General/Conceptual/WatchKitProgrammingGuide/BasicSupport.html#//apple_ref/doc/uid/TP40014969-CH18-SW2
     *
     * @param string $title The title displayed in the short look notification
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get the title of a short look Apple Watch notification.
     *
     * @return string The title displayed in the short look notification
     */
    public function getTitle()
    {
        return $this->title;
    }

        /**
     * Set the subtitle of a secondary description on iOS 10.0+ and watchOS 3.0+
     * See https://developer.apple.com/reference/usernotifications/unmutablenotificationcontent/1649873-subtitle
     *
     * @param string $subTitle the subtitle of a secondary description
     */
    public function setSubTitle($subTitle)
    {
        $this->subTitle = $subTitle;
    }

    /**
     * Get the subtitle of a secondary description on iOS 10.0+ and watchOS 3.0+
     *
     * @return string the subtitle of a secondary description on
     */
    public function getSubTitle()
    {
        return $this->subTitle;
    }
    /**
     * Get the payload dictionary.
     *
     * @return array The payload dictionary.
     */
    protected function getPayloadDictionary()
    {
        $payload = parent::getPayloadDictionary();

        $payload['aps']['alert'] = [];

        if (isset($this->text) && !isset($this->locKey)) {
            $payload['aps']['alert']['body'] = (string)$this->text;
        }

        if (isset($this->actionLocKey)) {
            $payload['aps']['alert']['action-loc-key'] = $this->actionLocKey == '' ?
                null : (string)$this->actionLocKey;
        }

        if (isset($this->locKey)) {
            $payload['aps']['alert']['loc-key'] = (string)$this->locKey;
        }

        if (isset($this->locArgs)) {
            $payload['aps']['alert']['loc-args'] = $this->locArgs;
        }

        if (isset($this->launchImage)) {
            $payload['aps']['alert']['launch-image'] = (string)$this->launchImage;
        }

        if (isset($this->title)) {
            $payload['aps']['alert']['title'] = (string)$this->title;
        }

        if (isset($this->subTitle)) {
            $payload['aps']['alert']['subtitle'] = (string)$this->subTitle;
        }
        return $payload;
    }
}
