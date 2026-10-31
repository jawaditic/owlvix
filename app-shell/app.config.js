// Expo config for an app made with the Owlvix mobile app builder (/mobile-app-builder).
// scripts/prepare.mjs writes shell.json (the app's settings and content) and the icon and splash images before each build.
// Without shell.json (local testing) the demo settings in shell.example.json are used.
const fs = require('fs');
const path = require('path');

const file = fs.existsSync(path.join(__dirname, 'shell.json')) ? 'shell.json' : 'shell.example.json';
const shell = JSON.parse(fs.readFileSync(path.join(__dirname, file), 'utf8'));

module.exports = {
    name: shell.name,
    slug: 'owlvix-app',
    version: shell.versionName,
    orientation: 'default',
    icon: './assets/icon.png',
    android: {
        package: shell.package,
        versionCode: shell.versionCode,
        adaptiveIcon: { foregroundImage: './assets/adaptive.png', backgroundColor: shell.colors.icon },
        // The app needs only the internet. Leave out what Expo adds by default, so Google Play
        // doesn't ask the owner to explain permissions the app never uses.
        permissions: ['android.permission.INTERNET'],
        blockedPermissions: [
            'android.permission.SYSTEM_ALERT_WINDOW',
            'android.permission.READ_EXTERNAL_STORAGE',
            'android.permission.WRITE_EXTERNAL_STORAGE',
            'android.permission.RECORD_AUDIO',
            'android.permission.VIBRATE',
        ],
    },
    ios: { bundleIdentifier: shell.package, buildNumber: String(shell.versionCode) },
    plugins: [
        ['expo-splash-screen', { image: './assets/splash.png', imageWidth: 180, resizeMode: 'contain', backgroundColor: shell.colors.splash }],
        ['expo-build-properties', { android: { usesCleartextTraffic: false, enableMinifyInReleaseBuilds: true, enableShrinkResourcesInReleaseBuilds: true } }],
        './plugins/with-release-signing',
    ],
    extra: { shell: { ...shell, icon: undefined } },
};
