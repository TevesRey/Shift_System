// Minimal service worker: makes ShiftMate installable. It never caches
// pages or API data, so schedules are always fresh.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
