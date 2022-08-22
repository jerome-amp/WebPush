# WebPush

```

require 'WebPush.php';

$payload = json_encode(
[
	'tag' => 'tag',
	'body' => 'Never Gonna Let You Down',
	'title' => 'Never Gonna Give You Up',
	'image' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
	'timestamp' => time(),
	'data' =>
	[
		'link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
	]
]);

$subscription = json_encode(
[
	'endpoint' => 'https://fcm.googleapis.com/fcm/send/d878_GYf11Y:APA91bEYZRWkPyRqaRFqctDXeba3iqITuh8Y0BX5wxaIb3jA0soofDKGawX_BQ_JmnZk7zzgM2tGZSJbTEn1bquXsHdUHteec0qcIgTWz7e0RLSzEDebrzSBPAM0S02SFvx5oaYBSV_l',
	'keys' =>
	[
		'p256dh' => 'BJoIeXhp5x1eJO0afL8QO7aiY99-rFr0ma51codIpj1mss2CoSz1xgCpDpMZyZnkaijGL_Htf7Ms4JF8lUUYrpY',
		'auth' => 'LxUwdfgBxopsqEXbz3FfFQ'
	]
]);

(new WebPush($payload, $subscription))->send();

```

## Author

**Jérôme Taillandier**

## License

This project is licensed under the WTFPL License - see the [LICENSE.md](LICENSE.md) file for details
