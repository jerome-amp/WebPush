<?php

if($subscription = file_get_contents('php://input'))
{
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
	
	(new WebPush($payload, $subscription))->send();
	
	exit;
}

?><script>

navigator.serviceWorker.register('sw.js');

navigator.serviceWorker.ready.then(function(registration)
{
	return registration.pushManager.getSubscription().then(function(subscription)
	{
		if(subscription) return subscription;
		
		return registration.pushManager.subscribe(
		{
			userVisibleOnly: true,
			applicationServerKey: 'YOUR-PUBLIC-SERVER-KEY-IN-X9.62-FORMAT'
		});
	});
})
.then(function(subscription)
{
	return JSON.stringify(subscription);
})
.then(function(data)
{
	document.body.innerHTML = data;
	
	fetch(new Request('', {method: 'POST', body: data}));
});

</script>