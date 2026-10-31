// Copies the pictures scripts/prepare.mjs packed (bundled-img/) into the Android app's assets as owlvix-img/, where
// the app reads them as asset:/owlvix-img/<name> (content.js).
const fs = require('fs');
const path = require('path');
const { withDangerousMod } = require('expo/config-plugins');

module.exports = config => withDangerousMod(config, ['android', async cfg => {
    const src = path.join(cfg.modRequest.projectRoot, 'bundled-img');
    if (!fs.existsSync(src)) return cfg;
    const dest = path.join(cfg.modRequest.platformProjectRoot, 'app/src/main/assets/owlvix-img');
    fs.mkdirSync(dest, { recursive: true });
    for (const name of fs.readdirSync(src)) {
        if (/^[a-f0-9]{20}\.(jpg|png|webp)$/.test(name)) fs.copyFileSync(path.join(src, name), path.join(dest, name));
    }
    return cfg;
}]);
