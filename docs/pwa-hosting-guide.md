# GemData PWA Hosting Guide

## Hosting requirements
- Serve GemData over HTTPS.
- Keep the app under a stable public origin and base path.
- Ensure `manifest.json`, `service-worker.js`, and icon assets are publicly reachable.

## Config updates
- Set `app.environment` to `production`.
- Set `app.public_origin` to the live domain.
- Keep `app.base_url` aligned with the deployed subdirectory.
- Keep `mobile.webview_origin` aligned with the production URL used by Capacitor.

## SSL and service worker
- Service workers require HTTPS in production.
- If caches behave unexpectedly after deployment, bump the version constant in `service-worker.js`.
- Avoid serving aggressive no-cache headers on the manifest and service worker files.

## Verification checklist
- Confirm installability in Chrome.
- Confirm offline page loads when the network is disabled.
- Confirm financial actions are blocked cleanly while offline instead of being queued for replay.
- Confirm API endpoints are not cached as successful offline responses.

## Push notifications preparation
- Current config only prepares the app for notifications.
- Add a real push provider and permission request flow in a later phase.
