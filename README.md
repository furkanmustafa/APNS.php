APNS.php
========

Simple APNS Client for php. Works. Doesn't have 3589454 files and abstract classes in it.

```php
require_once('APNSMessage.php');
require_once('APNS.php');

APNS::$Defaults['certificateFile'] = 'push-prod.pem';
APNS::$Defaults['certificatePass'] = 'mycertificatepasswordifiprotecteditwithapasswordatall';

$message = new APNSMessage('49aaa3feb1dfe5f7fa91a0e9bedddddda760a54c08abaa376a9b30006dec2ccc');
$message->alert = "Hello Computer!";
$message->badge = 2;
$message->sound = 'default';

echo "Sending..\n";
APNS::Connection([ 'stage' => APNS::PRODUCTION ])->send($message);
APNS::Connection([ 'stage' => APNS::PRODUCTION ])->close();
echo "Done!\n";
```

You usually won't need it (in linux), but in case you need it;

```php
// I've downloaded it from: https://www.entrust.net/downloads/binary/entrust_2048_ca.cer
APNS::$Defaults['caFile'] = 'entrust_2048_ca.cer';
```
