<?php

class WebPush
{
	private string $post;
	
	private string $salt;
	private string $encoding;
	private string $endpoint;
	
	private string $client_public;
	private string $random_public;
	
	private string $server_email = 'rick@astley.com';
	private string $server_public = 'YOUR-PUBLIC-SERVER-KEY-IN-X9.62-FORMAT';
	private string $server_private = 'YOUR-PRIVATE-SERVER-KEY-IN-X9.62-FORMAT';
	
	public function __construct(string $payload, string $subscription)
	{
		$this->salt = random_bytes(16);
		
		$subscription = json_decode($subscription);
		
		$this->endpoint = $subscription->endpoint;
		$this->encoding = $subscription->contentEncoding ?? 'aesgcm';
		
		$this->keygen($random_private, $random_public);
		
		$this->client_public = $this->b64_decode($subscription->keys->p256dh);
		$this->random_public = $this->x962_encode($random_public);
		
		$ikm = openssl_pkey_derive($this->x962_decode($this->client_public), $random_private, 256);
		$prk = hash_hkdf('sha256', $ikm, 32, $this->info('prk'), $this->b64_decode($subscription->keys->auth));
		$cek = hash_hkdf('sha256', $prk, 16, $this->info('cek'), $this->salt);
		$nonce = hash_hkdf('sha256', $prk, 12, $this->info('nonce'), $this->salt);
		
		$tag = '';
		$header = '';
		
		$cipher = openssl_encrypt($this->padding($payload), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag).$tag;
		
		if($this->encoding == 'aes128gcm')
		{
			$header = $this->salt.pack('N*', 4096).pack('C*', mb_strlen($this->random_public, '8bit')).$this->random_public;
		}
		
		$this->post = $header.$cipher;
	}
	
	private function keygen(mixed &$private, mixed &$public): void
	{
		$private = openssl_pkey_new(
		[
			'curve_name' => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'config' => 'C:\Xampp\php\extras\ssl\openssl.cnf'
		]);
		
		$public = openssl_pkey_get_public(openssl_pkey_get_details($private)['key']);
	}
	
	private function info(string $type): string
	{
		if($this->encoding == 'aesgcm')
		{
			$context = chr(0).chr(0).'A'.$this->client_public.chr(0).'A'.$this->random_public;
			
			if($type == 'prk') return 'Content-Encoding: auth'.chr(0);
			if($type == 'cek') return 'Content-Encoding: aesgcm'.chr(0).'P-256'.$context;
			if($type == 'nonce') return 'Content-Encoding: nonce'.chr(0).'P-256'.$context;
		}
		else if($this->encoding == 'aes128gcm')
		{
			if($type == 'prk') return 'WebPush: info'.chr(0).$this->client_public.$this->random_public;
			if($type == 'cek') return 'Content-Encoding: aes128gcm'.chr(0);
			if($type == 'nonce') return 'Content-Encoding: nonce'.chr(0);
		}
	}
	
	private function padding(string $payload): string
	{
		$length = mb_strlen($payload, '8bit');
		$padding = 3052 - $length;
		
		if($this->encoding == 'aesgcm')
		{
			return pack('n*', $padding).str_pad($payload, $padding+$length, chr(0), STR_PAD_LEFT);
		}
		else if($this->encoding == 'aes128gcm')
		{
			return str_pad($payload.chr(2), $padding+$length, chr(0), STR_PAD_RIGHT);
		}
	}
	
	private function b64_encode(string $string): string
	{
		return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
	}
	
	private function b64_decode(string $string): string
	{
		return base64_decode(strtr($string, '-_', '+/'));
	}
	
	private function x962_encode(OpenSSLAsymmetricKey $key): string
	{
		$ec = openssl_pkey_get_details($key)['ec'];
		
		return $ec['d'] ?? "\04".$ec['x'].$ec['y'];
	}
	
	private function x962_decode(string $key): OpenSSLAsymmetricKey
	{
		$ec = ['curve_name' => 'prime256v1'];
		
		if(mb_strlen($key, '8bit') == 65)
		{
			$ec['x'] = mb_substr($key, 1, 32, '8bit');
			$ec['y'] = mb_substr($key, 33, 32, '8bit');
		}
		else $ec['d'] = $key;
		
		return openssl_pkey_new(['ec' => $ec]);
	}
	
	public function send(): int
	{
		$headers =
		[
			'TTL: 2419200',
			'Content-Type: application/octet-stream',
			'Content-Length: '.mb_strlen($this->post, '8bit'),
			'Content-Encoding: '.$this->encoding
		];
		
		$jwt = $this->jwt();
		
		if($this->encoding == 'aesgcm')
		{
			$headers[] = 'Crypto-Key: dh='.$this->b64_encode($this->random_public).';p256ecdsa='.$this->server_public;
			$headers[] = 'Encryption: salt='.$this->b64_encode($this->salt);
			$headers[] = 'Authorization: WebPush '.$jwt;
		}
		else if($this->encoding == 'aes128gcm')
		{
			$headers[] = 'Authorization: vapid t='.$jwt.', k='.$this->server_public;
		}
		
		$curl = curl_init($this->endpoint);
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $this->post);
		
		curl_exec($curl);
		
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		curl_close($curl);
		
		return $code;
	}
	
	private function jwt(): string
	{
		$header = $this->b64_encode(json_encode(
		[
			'typ' => 'JWT',
			'alg' => 'ES256'
		]));
		
		$payload = $this->b64_encode(json_encode(
		[
			'aud' => parse_url($this->endpoint, PHP_URL_SCHEME).'://'.parse_url($this->endpoint, PHP_URL_HOST),
			'exp' => strtotime('+12 hour'),
			'sub' => $this->server_email
		]));
		
		openssl_sign($header.'.'.$payload, $signature, $this->x962_decode($this->b64_decode($this->server_private)), 'sha256');
		
		return $header.'.'.$payload.'.'.$this->b64_encode($this->asn1_encode($signature));
	}
	
	private function asn1_encode(string $signature): string
	{
		$data = bin2hex($signature);
		$position = 6;
		
		$length = hexdec($this->asn1_read($data, $position, 2))*2;
		$r = $this->asn1_retrieve($this->asn1_read($data, $position, $length));
		
		$position += 2;
		
		$length = hexdec($this->asn1_read($data, $position, 2))*2;
		$s = $this->asn1_retrieve($this->asn1_read($data, $position, $length));
		
		return hex2bin(str_pad($r, 64, '0', STR_PAD_LEFT).str_pad($s, 64, '0', STR_PAD_LEFT));
	}
	
	private function asn1_read(string $data, int &$position, int $length): string
	{
		$data = mb_substr($data, $position, $length, '8bit');
		$position += $length;
		
		return $data;
	}
	
	private function asn1_retrieve(string $data): string
	{
		while(0 === mb_strpos($data, '00', 0, '8bit') && mb_substr($data, 2, 2, '8bit') > '7f')
		{
			$data = mb_substr($data, 2, null, '8bit');
		}
		
		return $data;
	}
}
