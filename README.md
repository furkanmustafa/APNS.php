APNS.php
========

Simple APNS Client for php. Works. Doesn't have 3589454 files and abstract classes in it.

*It now supports up to 2048bytes payloads for iOS8+*

As documented here: https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html

```php
require_once('FMAPNS/Connection.php');
require_once('FMAPNS/Message.php');

APNS::$Defaults['certificateFile'] = 'push-prod.pem';
APNS::$Defaults['certificatePass'] = 'mycertificatepasswordifiprotecteditwithapasswordatall';
// APNS::$Defaults['stage'] = APNS::DEVELOPMENT; // Default is PRODUCTION

$message = new APNSMessage('49aaa3feb1dfe5f7fa91a0e9bedddddda760a54c08abaa376a9b30006dec2ccc');
$message->alert = "Hello Computer!";
$message->badge = 2;
$message->sound = 'default';
// Pass extra metadata in `userinfo` property of message
$message->userinfo['screen_id'] = 25;

echo "Sending..\n";
APNS\Connection::Shared()->send($message);
APNS\Connection::Shared()->close();
echo "Done!\n";
```

You usually won't need it (in linux), but in case you need it;

```php
APNS::$Defaults['caFile'] = 'entrust_2048_ca.cer';
// I've downloaded it from: https://www.entrust.net/downloads/binary/entrust_2048_ca.cer
```

TODO
====

 - [ ] Feedback Service poller
 - [ ] Cleanup Message class and move protocol related stuff into Connection class

LICENSE
=======

See [LICENSE](LICENSE)
