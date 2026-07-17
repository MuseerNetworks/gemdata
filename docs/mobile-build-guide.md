# GemData Mobile Build Guide

## Overview
GemData remains a server-rendered PHP app. The mobile app wrapper should load the hosted HTTPS app URL inside Capacitor rather than trying to bundle PHP pages as static assets.

## Prerequisites
- Host GemData on a public HTTPS domain.
- Set `app.public_origin` and `mobile.webview_origin` to the production URL.
- Install Node.js with npm available on PATH.
- Install Android Studio for Play Store builds.
- Use macOS with Xcode for App Store builds. iOS signing and archive uploads cannot be completed on Windows.
- Create Apple Developer and Google Play Console accounts before release signing.

## Capacitor setup
1. From the project root, run `npm install`.
2. Update `capacitor.config.json`:
   - `appId`
   - `appName`
   - `server.url`
3. Run `npx cap add android`
4. Run `npx cap add ios`
5. Run `npx cap sync`

For this project, `capacitor.config.json` is configured as a hosted webview app. The native apps load the production HTTPS site instead of bundling PHP files into the mobile binary.

## Android build
1. Run `npx cap open android`
2. In Android Studio, allow Gradle sync to complete.
3. Set app icons and splash assets in the Android project resources.
4. Build:
   - **APK**: Build > Build Bundle(s) / APK(s) > Build APK(s)
   - **AAB**: Build > Generate Signed Bundle / APK > Android App Bundle
5. Configure release signing before Play Store upload.

The Play Store production upload should be an Android App Bundle (`.aab`), not only an APK.

## iOS build
1. Run `npx cap open ios`
2. Open the generated workspace in Xcode.
3. Set app icons and launch screen assets.
4. Configure signing/team settings.
5. Build and archive through Product > Archive for App Store Connect.

The App Store upload must be produced from Xcode on macOS with a valid signing team, bundle identifier, app icon set, and privacy details.

## Icons and splash
- Reuse the PWA icon family as the source for native icons.
- Export platform-specific sizes through Android Studio / Xcode asset catalogs.
- Keep the same GemData background and theme colors for consistency.

## Permissions
- Add notification permissions only when web push or native push is enabled.
- Do not request unnecessary device permissions for the current webview-based build.

## Store checklist
- Confirm `server.url` points to the live HTTPS domain.
- Confirm login, signup, wallet funding, transaction history, and one successful purchase work inside the Android and iOS WebViews.
- Add native app icons and splash screens for both platforms.
- Prepare privacy policy, support URL, app category, screenshots, and age rating answers.
- Keep external payment, wallet, and user data disclosures consistent with App Store and Play Store policies.

## Routing and API URLs
- All links must stay base-URL safe whether the app is deployed at the domain root or under a subdirectory.
- API calls should resolve against the hosted origin, never `localhost`.
- Test login, wallet funding, and one purchase inside the WebView before release.
