<?php

class APNSMessage {
	
	public $token               = null;
  
	public $alert               = null;
	public $sound               = null;
	public $badge               = null;
	
	public $localizedAlertKey   = null;
	public $localizedActionKey  = null;
	public $localizedAlertArgs  = null;
	public $launchImage         = null;
	
	public $userinfo            = null;
  
	function __construct($token = null) {
		$this->token = $token;
	}
	static function CopyForToken($token) {
		$copy = clone $this;
		$copy->token = $token;
		
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
		// Encode the payload as JSON
		$payload = $this->json();

		// Build the binary notification
		$msg = chr(0) . pack('n', 32);
		if (strlen($this->token) == 32) {
			$msg .= $this->token;
		} else if (strlen($this->token) == 64) {
			$msg .= pack('H*', $this->token);
		} else {
			throw new Exception('Invalid Token');
		}
		$msg .= pack('n', strlen($payload)) . $payload;
		if (strlen($msg) > 255) {
			throw new Exception('Payload too big');
		}
		return $msg;
	}
  
}
