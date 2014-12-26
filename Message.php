<?php

namespace FMAPNS;
use \Exception;

class Message {
  
  const  POWER_SAVING         = 5;
  const  IMMEDIATE            = 10;
  
  public $token               = null;
  public $realm               = null;
  
  public $alert               = null;
  public $sound               = null;
  public $badge               = null;
  
  public $localizedAlertKey   = null;
  public $localizedActionKey  = null;
  public $localizedAlertArgs  = null;
  public $launchImage         = null;
  
  public $userinfo            = null;
  
  public $priority            = self::IMMEDIATE;
  public $expiresAt           = 0; // unixtime, 0 = never expires
  public $identifier          = 0; // will be set to a random number, override if you want to track status
  
  function __construct($token = null, $realm = null) {
    static $randomSequence = 0;
    if ($randomSequence == 0)
      $randomSequence = mt_rand();
    
    $this->setToken($token);
    $this->realm = $realm;
    
    $this->identifier = ++$randomSequence;
  }
  function setToken($token) {
    if (!$token) {
      $this->token = null;
      return;
    }
    if (strlen($token) == 32) {
      $this->token = bin2hex($token);
    } else if (strlen($token)==64) {
      $this->token = $token;
    } else if (strlen(base64_decode($token)) == 32) {
      $this->token = bin2hex($token);
    } else {
      throw new Exception('Invalid APNS Token');
    }
  }
  function to($token) {
    $copy = clone $this;
    $copy->setToken($token);
    return $copy;
  }

  function data() {
    $body = [
      'aps' => []
    ];

    if ($this->alert && !($this->localizedAlertArgs || $this->localizedAlertKey || $this->localizedActionKey || $this->launchImage)) {
      $body['aps']['alert'] = $this->alert;
    } else if ($this->localizedAlertArgs || $this->localizedAlertKey || $this->localizedActionKey || $this->launchImage) {
      if ($this->localizedAlertKey)
        $_alert['loc-key'] = $this->localizedAlertKey;
      if ($this->localizedAlertArgs)
        $_alert['loc-args'] = $this->localizedAlertArgs;
      if ($this->localizedActionKey)
        $_alert['action-loc-key'] = $this->localizedActionKey;
      if ($this->launchImage)
        $_alert['launch-image'] = $this->launchImage;
      if ($this->alert)
        $_alert['body'] = $this->alert;
  
      $body['aps']['alert'] = $_alert;
    }

    if ($this->sound !== null) {
      $body['aps']['sound'] = $this->sound;
    }

    if ($this->badge !== null) {
      $body['aps']['badge'] = $this->badge;
    }

    if ($this->userinfo && is_array($this->userinfo))
      $body = array_merge($body, $this->userinfo);

    return $body;
  }
  
  function json() {
    return json_encode($this->data());
  }
  
  function payload() {
    
    $payload = $this->json();
    
    // Old Protocol
    
    // Build the binary notification
    // $msg = chr(0) . pack('n', 32);
    // if (strlen($this->token) == 32) {
    //   $msg .= $this->token;
    // } else if (strlen($this->token) == 64) {
    //   $msg .= pack('H*', $this->token);
    // } else {
    //   throw new Exception('Invalid Token');
    // }
    // $msg .= pack('n', strlen($payload)) . $payload;
    // if (strlen($msg) > 255) {
    //   throw new Exception('Payload too big');
    // }
    
    // New Protocol
    
    $frame = '';
    // prepare the frame
    // 1. token
    $item = pack('H*', $this->token);
    $frame .= chr(1) . pack('n', strlen($item)) . $item;

    // 2. payload
    if (strlen($payload) > 2048)
      throw new Exception('Payload too big');
    
    $item = $payload;
    $frame .= chr(2) . pack('n', strlen($item)) . $item;

    // 3. identifier (4 bytes) = 0?
    $item = pack('N', $this->identifier);
    $frame .= chr(3) . pack('n', strlen($item)) . $item;

    // 4. expiration (4 bytes) = 0
    $item = pack('N', $this->expiresAt);
    $frame .= chr(4) . pack('n', strlen($item)) . $item;

    // 5. priority ( 5 = power-saving, 10 = immediate )
    $item = chr($this->priority);
    $frame .= chr(5) . pack('n', strlen($item)) . $item;
    
    // Pack the frame
    $msg = chr(2) . pack('N', strlen($frame)) . $frame;
    
    return $msg;
  }
  
  function send() {
    Connection::SendMessage($this); // Using the default connection
  }
  function sendTo($otherToken) {
    Connection::SendMessage($this->to($otherToken)); // Using the default connection
  }
}
