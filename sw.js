
self.addEventListener('install', (event) =>
{
	self.skipWaiting();
});

self.addEventListener('push', (event) =>
{
	json = event.data.json();
	
	self.registration.showNotification(json.title,
	{
		tag: json.tag,
		body: json.body,
		data: json.data,
		image: json.image,
		timestamp: json.time,
		requireInteraction: true,
		renotify: true
	});
});

self.addEventListener('notificationclick', (event) =>
{
	event.notification.close();
	
	event.waitUntil(
		clients.matchAll({type: 'window', includeUncontrolled: true}).then((list) =>
		{
			for(const client of list)
			{
				if(client.url === event.notification.data.link) return client.focus();
			}
			
			return clients.openWindow(event.notification.data.link);
		})
	);
});
