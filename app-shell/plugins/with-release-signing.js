// Signs release builds with the app's own upload key (prepare.mjs puts its path and password in OWLVIX_UPLOAD_*
// environment variables). Without them the build falls back to the debug key, which Google Play refuses.
const { withAppBuildGradle } = require('expo/config-plugins');

module.exports = config => withAppBuildGradle(config, cfg => {
    let g = cfg.modResults.contents;
    if (g.includes('OWLVIX_UPLOAD_STORE_FILE')) return cfg;
    const before = g;
    g = g.replace(/signingConfigs\s*\{/, `signingConfigs {
        release {
            if (System.getenv('OWLVIX_UPLOAD_STORE_FILE')) {
                storeFile file(System.getenv('OWLVIX_UPLOAD_STORE_FILE'))
                storePassword System.getenv('OWLVIX_UPLOAD_STORE_PASSWORD')
                keyAlias System.getenv('OWLVIX_UPLOAD_KEY_ALIAS')
                keyPassword System.getenv('OWLVIX_UPLOAD_STORE_PASSWORD')
            }
        }`);
    g = g.replace(/(buildTypes\s*\{[\s\S]*?release\s*\{[\s\S]*?)signingConfig\s+signingConfigs\.debug/,
        "$1signingConfig System.getenv('OWLVIX_UPLOAD_STORE_FILE') ? signingConfigs.release : signingConfigs.debug");
    if (g === before || !g.includes("? signingConfigs.release")) {
        throw new Error('with-release-signing: android/app/build.gradle has changed shape; update the plugin.');
    }
    cfg.modResults.contents = g;
    return cfg;
});
