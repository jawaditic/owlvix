// Owlvix's own icons, drawn with react-native-svg: the same drawings as the website (resources/icons/icons.json),
// a line in the given colour with a light tint of it filling the shape. Keyed by the names app content uses
// (app-shell/icons.json is the list the server accepts: "phone" is a handset, "location" a map pin), plus a few
// the app draws itself. Each shape: [kind (p path, r rect, c circle, e ellipse), 'd' tinted / 'f' solid / '' line, props].
import { Text } from 'react-native';
import Svg, { Circle, Ellipse, Path, Rect } from 'react-native-svg';

const SHAPES = {
    home: [['p','d',{d:'M4 10.5L12 4l8 6.5V20H4z'}],['p','',{d:'M10 20v-5.5h4V20'}]],
    grid: [['r','d',{x:3.5,y:3.5,width:7,height:7,rx:1.5}],['r','d',{x:13.5,y:3.5,width:7,height:7,rx:1.5}],['r','d',{x:3.5,y:13.5,width:7,height:7,rx:1.5}],['r','d',{x:13.5,y:13.5,width:7,height:7,rx:1.5}]],
    store: [['p','d',{d:'M4.5 10v10h15V10'}],['p','',{d:'M3 10l1.6-5.5h14.8L21 10z'}],['p','',{d:'M10 20v-5h4v5'}]],
    cart: [['p','d',{d:'M6 6h15l-1.8 8.2a2 2 0 0 1-2 1.6H9.3a2 2 0 0 1-2-1.6z'}],['p','',{d:'M3 3h2.5L6 6M9.5 20.5h.01M17.5 20.5h.01'}]],
    tag: [['p','d',{d:'M3 12.2V4a1 1 0 0 1 1-1h8.2a1 1 0 0 1 .7.3l8 8a1 1 0 0 1 0 1.4l-8.2 8.2a1 1 0 0 1-1.4 0l-8-8a1 1 0 0 1-.3-.7z'}],['p','',{d:'M7.5 7.5h.01'}]],
    food: [['c','d',{cx:13,cy:12.5,r:6}],['p','',{d:'M3 4v5a2 2 0 0 0 2 2v9M5 4v4M7 4v5a2 2 0 0 1-2 2M21 20V4c-1.2.5-2 2-2 4.5V12h2'}]],
    calendar: [['r','d',{x:3,y:5,width:18,height:16,rx:2.5}],['p','',{d:'M3 10h18M8 3v4M16 3v4M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01'}]],
    image: [['r','d',{x:3,y:4,width:18,height:16,rx:2.5}],['c','',{cx:9,cy:9.5,r:1.8}],['p','',{d:'M21 16l-5-5-9 9'}]],
    play: [['p','d',{d:'M7 4.5v15l12.5-7.5z'}]],
    book: [['p','d',{d:'M4 4.5A1.5 1.5 0 0 1 5.5 3H20v15H5.5A1.5 1.5 0 0 0 4 19.5z'}],['p','',{d:'M4 19.5A1.5 1.5 0 0 0 5.5 21H20M8 7.5h8'}]],
    search: [['c','d',{cx:10.5,cy:10.5,r:6.5}],['p','',{d:'M15.5 15.5L21 21'}]],
    heart: [['p','d',{d:'M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z'}]],
    star: [['p','d',{d:'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z'}]],
    bell: [['p','d',{d:'M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z'}],['p','',{d:'M10 20.5a2 2 0 0 0 4 0'}]],
    chat: [['p','d',{d:'M4 5h16v11H9l-5 4z'}],['p','',{d:'M8 10h8M8 13h5'}]],
    phone: [['p','d',{d:'M5 3.5h3.5l1.8 4.5-2.3 1.5a11 11 0 0 0 6.5 6.5l1.5-2.3 4.5 1.8V19a2 2 0 0 1-2 2A16.5 16.5 0 0 1 3 5.5a2 2 0 0 1 2-2z'}]],
    mail: [['r','d',{x:3,y:5,width:18,height:14,rx:2}],['p','',{d:'M3.5 6.5l8.5 6.5 8.5-6.5'}]],
    location: [['p','d',{d:'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z'}],['c','',{cx:12,cy:9.5,r:2.5}]],
    user: [['c','d',{cx:12,cy:8,r:4}],['p','',{d:'M4.5 20.5a7.5 7.5 0 0 1 15 0'}]],
    info: [['c','d',{cx:12,cy:12,r:9}],['p','',{d:'M12 11v5M12 7.5h.01'}]],
    globe: [['c','d',{cx:12,cy:12,r:9}],['p','',{d:'M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z'}]],
    close: [['p','',{d:'M6 6l12 12M18 6L6 18'}]],
    check: [['c','d',{cx:12,cy:12,r:9}],['p','',{d:'M8 12.5l2.8 2.8L16.5 9.5'}]],
    tick: [['p','',{d:'M5 12.5l4.5 4.5L19 7.5'}]],
    party: [['p','d',{d:'M4 20l4-11 7 7z'}],['p','',{d:'M13 4.5c.5 1.5 0 2.5-1 3M19.5 11c-1.5-.5-2.5 0-3 1M15.5 3v.01M21 8.5v.01M17.5 6.5l-2 2M20.5 3.5h.01'}]],
};

/** Emoji that older content may hold → the icon that stands for it. */
const EMOJI = {
    '🏠': 'home', '🏡': 'home', '🛍': 'store', '🛍️': 'store', '🏪': 'store', '🛒': 'cart', '🏷': 'tag', '🏷️': 'tag',
    '🍽': 'food', '🍽️': 'food', '📅': 'calendar', '🖼': 'image', '🖼️': 'image', '📷': 'image', '📸': 'image',
    '▶': 'play', '▶️': 'play', '🎬': 'play', '🎥': 'play', '📘': 'book', '📖': 'book', '🔎': 'search', '🔍': 'search',
    '❤': 'heart', '❤️': 'heart', '💖': 'heart', '💚': 'heart', '⭐': 'star', '★': 'star', '🔔': 'bell', '💬': 'chat',
    '📞': 'phone', '☎': 'phone', '☎️': 'phone', '✉': 'mail', '✉️': 'mail', '📧': 'mail', '📍': 'location', '👤': 'user',
    'ℹ': 'info', 'ℹ️': 'info', '🌐': 'globe', '🌍': 'globe', '✕': 'close', '✖': 'close', '❌': 'close', '✅': 'check',
    '✓': 'tick', '🎉': 'party',
};

const KIND = { p: Path, r: Rect, c: Circle, e: Ellipse };

/**
 * An icon by name. Older content may hold an emoji instead: a known one draws its icon, any other short text is
 * shown as it is, and an unknown name falls back to the home icon.
 */
export function Icon({ name, color, size = 24, width = 1.8 }) {
    const key = Object.prototype.hasOwnProperty.call(SHAPES, name) ? name : EMOJI[name];
    if (!key && typeof name === 'string' && name && !/^[a-z-]+$/.test(name)) {
        return <Text style={{ fontSize: size * 0.8, lineHeight: size, color }} allowFontScaling={false}>{Array.from(name).slice(0, 4).join('')}</Text>;
    }
    return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={width} strokeLinecap="round" strokeLinejoin="round">
            {SHAPES[key || 'home'].map(([k, look, props], i) => {
                const Shape = KIND[k];
                return <Shape key={i} {...props} {...(look === 'd' ? { fill: color, fillOpacity: 0.16 } : look === 'f' ? { fill: color } : null)} />;
            })}
        </Svg>
    );
}
