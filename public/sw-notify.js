/*
|--------------------------------------------------------------------------
| Notification service worker
|--------------------------------------------------------------------------
| The smallest worker that does one job: let a phone show the pop-up, and let
| tapping it open the right screen.
|
| It deliberately does NOT cache anything. A hotel PMS shows live room status;
| an offline cache here would show yesterday's house and somebody would sell a
| room that is already occupied. If offline support is ever wanted it belongs
| in its own worker with its own thinking, not bolted onto this one.
*/

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('notificationclick', function (event) {
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.notification.close();

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            // Reuse a tab that is already on this site rather than opening a
            // ninth copy of the PMS on the desk's browser.
            for (var i = 0; i < windows.length; i++) {
                var client = windows[i];

                if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }

            return undefined;
        })
    );
});
