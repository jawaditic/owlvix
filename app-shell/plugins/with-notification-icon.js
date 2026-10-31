// Push notifications: Android shows a small white silhouette in the status bar. scripts/prepare.mjs writes it as
// assets/notification.png (drawn by the builder from the app's logo or letters); this copies it into the Android
// project as @drawable/notification_icon and adds @color/notification_icon_color (the app's main colour), which
// @react-native-firebase/messaging points Firebase to.
const fs = require('fs');
const path = require('path');
const { withDangerousMod, withAndroidColors } = require('expo/config-plugins');

module.exports = (config, { color }) => {
    config = withDangerousMod(config, ['android', async cfg => {
        const src = path.join(cfg.modRequest.projectRoot, 'assets/notification.png');
        const dir = path.join(cfg.modRequest.platformProjectRoot, 'app/src/main/res/drawable');
        fs.mkdirSync(dir, { recursive: true });
        fs.copyFileSync(src, path.join(dir, 'notification_icon.png'));
        return cfg;
    }]);
    return withAndroidColors(config, cfg => {
        const res = cfg.modResults.resources;
        res.color = (res.color || []).filter(c => c.$.name !== 'notification_icon_color');
        res.color.push({ $: { name: 'notification_icon_color' }, _: color });
        return cfg;
    });
};
