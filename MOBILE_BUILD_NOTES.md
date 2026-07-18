# GemData Mobile Build Notes

## Verified Toolchain Versions (Local Environment)
- **Node.js**: v24.18.0
- **npm**: 11.16.0
- **Capacitor CLI**: 7.6.7
- **Java JDK**: OpenJDK Runtime Environment Temurin-21.0.7+6 (Location: `C:\Android\jdk21\extracted\jdk-21.0.7+6`)
- **Android SDK Location**: `C:\Android`
- **Android SDK Platforms Installed**: android-35, android-34 (auto-downloaded by Gradle)
- **Android SDK Build-Tools Installed**: 35.0.0, 34.0.0

## Native Target Output
- **Debug APK Location**: `C:\xampp\htdocs\gemdata\android\app\build\outputs\apk\debug\app-debug.apk`
- **Build Status**: Successful compile ✅
- **APK Size**: 3.86 MB

## Doctor Diagnostic Output (`npx cap doctor`)
- Installed Dependencies:
  - `@capacitor/cli`: 7.6.7
  - `@capacitor/core`: 7.6.7
  - `@capacitor/ios`: 7.6.7
  - `@capacitor/android`: 7.6.7
- Android Status: Looking great! 👌
- Xcode Status: Xcode is not installed (Windows Environment)

## iOS Environment Status
- **Mac Available**: Deferring iOS build to cloud CI (e.g. GitHub Actions, Codemagic) or physical Mac when available.

## Store Accounts Status
- **Play Store Developer Console**: Pending verification
- **Apple Developer Program**: Pending verification
- **Firebase Console Project**: Created / Pending Configuration
