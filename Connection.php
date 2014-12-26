<?php

namespace FMAPNS;
use \Exception;

Class Connection {
  
  const PRODUCTION                      = 'production';
  const DEVELOPMENT                     = 'development';
  
  // HOWTO: openssl pkcs12 -in apns-dev-cert.p12 -out apns-dev-cert.pem -nodes -clcerts
  public static $DevelopmentServer      = 'tls://gateway.sandbox.push.apple.com:2195';
  public static $ProductionServer       = 'tls://gateway.push.apple.com:2195';

  public $identifier                    = null; // optional. enables better sharing of instances
  
  public $stage                         = self::PRODUCTION;
  public $certificateFile               = null;
  public $caFile                        = null;
  public $certificatePass               = false;
  
  public $lastError                     = null;
  
  public static $Instances              = [];
  public static $DefaultInstance        = null;
  public $connection                    = null;
  public $server                        = null;
  public $exceptions                    = false;
  
  public $isConnected                   = false;
  
  public static $Defaults = [
    'stage' => self::PRODUCTION,
    'exceptions' => true,
    'caFile' => '/etc/ssl/certs/ca-certificates.crt'
  ];
  
  static function Server($stage = self::PRODUCTION) {
    if ($stage == self::PRODUCTION) {
      return self::$ProductionServer;
    } else {
      return self::$DevelopmentServer;
    }
  }
  static function SetDefault($key, $value) {
    self::$Defaults[$key] = $value;
  }
  static function Shared($options = []) {
    $options = array_merge(self::$Defaults, $options);
    if (!isset($options['server']))
      $options['server'] = self::Server($options['stage']);
    extract($options);
    
    // check existing connections
    foreach (self::$Instances as $instance) {
      if (isset($options['identifier']) && $instance->identifier === $options['identifier'])
        return $instance; // right away.
      
      if ($instance->stage != $stage) continue;
      if ($instance->server != $server) continue;
      if ($instance->exceptions != $exceptions) continue;
      if ($instance->certificateFile != $certificateFile) continue;
      if ($instance->certificatePass != $certificatePass) continue;
      if (!$instance->connection) continue;
      
      return $instance;
    }
    return new Connection($options);
  }
  
  function __construct($options) {
    $options = array_merge(self::$Defaults, $options);
    if (!isset($options['server']))
      $options['server'] = self::Server($options['stage']);
    
    if (isset($options['identifier']))
      $this->identifier = $options['identifier'];
    
    $this->stage = $options['stage'];
    $this->server = $options['server'];
    $this->exceptions = $options['exceptions'];
    $this->certificateFile = $options['certificateFile'];
    if (isset($options['certificatePass']))
      $this->certificatePass = $options['certificatePass'];
    if (isset($options['caFile']))
      $this->caFile = $options['caFile'];
    
    self::$Instances[] = $this;
    if (count(self::$Instances) == 1) {
      self::$DefaultInstance = $this;
    }
  }
  
  function connect() {
    $this->lastError = null;
    
    $ctx = stream_context_create();
    stream_context_set_option($ctx, 'ssl', 'local_cert', $this->certificateFile);
    if ($this->certificatePass)
      stream_context_set_option($ctx, 'ssl', 'passphrase', $this->certificatePass);
        if ($this->caFile)
      stream_context_set_option($ctx, 'ssl', 'cafile', $this->caFile);
    
    $this->connection = stream_socket_client($this->server, $err, $errstr, 60, 
      STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

    if (!$this->connection) {
      if ($this->exceptions)
        throw new Exception("Connection Failed: [{$err}] $errstr");
      return false;
    }

    stream_set_blocking($this->connection, false);

    $this->isConnected = true;

    return true;
  }
  function close() {
    if ($this->connection === null)
      return false;
    
    fclose($this->connection);
    $this->connection = null;
    
    foreach (self::$Instances as $idx => $instance) {
      if ($instance === $this) {
        unset(self::$Instances[$idx]);
        self::$Instances = array_values(self::$Instances);
        break;
      }
    }
    $this->isConnected = false;
    return true;
  }
  
  static function SendMessage(Message $message) {
    if ($message->realm) {
      $connection = self::Shared([ 'identifier' => $message->realm ]);
    } else {
      $connection = self::Shared();
    }
    $connection->send($message);
  }
  
  function sendClose(Message $message) {
    $this->send($message);
    $this->close();
  }
  
  function send(Message $message) {
    try {
      // Connect on demand.
      if (!$this->isConnected) {
        $this->connect();
      }
      $payload = $message->payload();
    } catch (Exception $e) {
      $this->lastError = "APNS-Error: Exception: ".$e->getMessage();
      if ($this->exceptions)
        throw $e;
      return false;
    }
    
    $result = fwrite($this->connection, $payload, strlen($payload));
    if (!$result) {
      $this->close();
      $this->lastError = "APNS-Error: Socket Error, Cannot send";
      if ($this->exceptions)
        throw new Exception('Sending APNS Payload Failed');
      return false;
    } else if ($result != strlen($payload)) {
      $this->lastError = "APNS-Error: Socket Error, Cannot send (byte count doesnt match)";
      if ($this->exceptions)
        throw new Exception('Sending APNS Payload Failed. Sent bytes:' . $result);
      return false;
    }
    
    $error = $this->readErrors(false);
    if ($error) {
      $this->lastError = $error;
      
      if ($this->exceptions)
        throw new Exception('APNS Server Exception ' . implode(', ', $this->lastError));
      return false;
    }
    
    return true;
  }
  
  function readErrors($blocking = false) {
    if (!feof($this->connection)) {
      $errorData = fread($this->connection, 6);
      if (!$errorData || strlen($errorData) == 0) return null;
      if (strlen($errorData) == 6) {
        return unpack("Ccmd/Ccode/Liden", $errorData);
      } else {
        return array('cmd'=>'0', 'code'=>0, 'iden'=>0);
      }
    }
    return null;
  }
  
  function __destruct() {
    $this->close();
  }
}
