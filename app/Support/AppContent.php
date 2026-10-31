<?php

namespace App\Support;

use App\Models\BuilderApp;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * What a mobile app shows: its tabs, and on each native screen a list of blocks (banner, text, list, gallery…)
 * that app-shell/blocks.js draws with React Native components. Checked here when the builder saves, then written
 * as a static file, {public_dir}/{app id}/content.json with the pictures beside it in img/, so the web server
 * hands it to phones without PHP. The app keeps a copy for offline use and asks again with If-None-Match.
 */
class AppContent
{
    /** Pages in an app; up to MAX_BAR of them sit in the bottom bar, and any of them can be in the ☰ menu too. */
    public const MAX_TABS = 15;

    public const MAX_BAR = 5;

    public const MAX_BLOCKS = 40;

    public const MAX_IMAGES = 60;

    /** Largest picture accepted (the builder shrinks them to 1280 px first, so they're usually far smaller). */
    public const MAX_IMAGE_BYTES = 2_500_000;

    /** New pictures in one save, together. */
    public const MAX_NEW_BYTES = 12_000_000;

    public const TYPES = ['hero', 'heading', 'text', 'image', 'button', 'list', 'gallery', 'contact', 'hours', 'video', 'divider',
        'slider', 'cards', 'reviews', 'faq', 'social', 'notice', 'spacer', 'products', 'booking', 'loyalty'];

    /** Social networks the "social" block links to (each value is the page's https address). */
    public const SOCIAL = ['instagram', 'facebook', 'tiktok', 'youtube', 'x', 'linkedin', 'pinterest', 'snapchat'];

    public const ACTIONS = ['url', 'tel', 'email', 'whatsapp', 'map', 'tab'];

    /** @var array<string, string> pictures added by this save: file name => bytes */
    private array $new = [];

    private int $newBytes = 0;

    /** @var array<string, true> */
    private array $used = [];

    private string $where = '';

    public function __construct(private ?BuilderApp $app = null) {}

    /**
     * Checks the tabs sent by the builder.
     *
     * @param  callable(string): ?string  $webUrl  checks a web page tab's address: the clean URL, or null when it isn't on the owner's site
     * @return array{tabs: array, images: array<string, string>}|string the clean tabs and the new pictures, or an error
     */
    public function clean(mixed $tabs, callable $webUrl): array|string
    {
        if (! is_array($tabs) || ! array_is_list($tabs) || $tabs === []) {
            return 'Your app needs at least one screen.';
        }
        if (count($tabs) > self::MAX_TABS) {
            return 'An app can have up to '.self::MAX_TABS.' pages.';
        }
        $count = count($tabs);
        $out = [];
        try {
            foreach ($tabs as $i => $tab) {
                if (! is_array($tab)) {
                    return 'Please check your tabs.';
                }
                $label = self::str($tab['label'] ?? '');
                if ($label === '' || mb_strlen($label) > 24) {
                    return 'Each page needs a short name (up to 24 characters).';
                }
                $icon = array_key_exists(is_string($tab['icon'] ?? null) ? $tab['icon'] : '', AppBuilder::icons()) ? $tab['icon'] : 'home';
                // In the bottom bar (the first five, unless the builder says otherwise) and/or in the ☰ menu (by
                // default the pages that aren't in the bar); a page in neither opens only from buttons on other pages
                $bar = array_key_exists('bar', $tab) ? (bool) $tab['bar'] : $i < self::MAX_BAR;
                $menu = array_key_exists('menu', $tab) ? (bool) $tab['menu'] : ! $bar;
                if (($tab['type'] ?? 'screen') === 'web') {
                    $url = $webUrl(self::str($tab['url'] ?? ''));
                    if (! $url) {
                        return 'Tab “'.$label.'” must open a page on your own website — add your website address under “More settings”.';
                    }
                    $out[] = ['label' => $label, 'icon' => $icon, 'type' => 'web', 'bar' => $bar, 'menu' => $menu, 'url' => $url];

                    continue;
                }
                $blocks = $tab['blocks'] ?? [];
                if (! is_array($blocks) || ! array_is_list($blocks)) {
                    return 'Please check the blocks on “'.$label.'”.';
                }
                if (count($blocks) > self::MAX_BLOCKS) {
                    return '“'.$label.'” has too many blocks (up to '.self::MAX_BLOCKS.').';
                }
                $clean = [];
                foreach ($blocks as $n => $block) {
                    $this->where = '“'.$label.'”, block '.($n + 1).': ';
                    $b = $this->block($block, $count);
                    if ($b !== null) {
                        $clean[] = $b;
                    }
                }
                $out[] = ['label' => $label, 'icon' => $icon, 'type' => 'screen', 'bar' => $bar, 'menu' => $menu, 'blocks' => $clean];
            }
        } catch (AppContentError $e) {
            return $e->getMessage();
        }
        if (count(array_filter(array_column($out, 'bar'))) > self::MAX_BAR) {
            return 'Up to '.self::MAX_BAR.' pages fit in the bottom bar — put the others in the ☰ menu only.';
        }
        if (count($this->used) > self::MAX_IMAGES) {
            return 'Your app has too many pictures (up to '.self::MAX_IMAGES.').';
        }

        return ['tabs' => $out, 'images' => $this->new];
    }

    /** One block, or null for an empty one (a heading with no text…). */
    private function block(mixed $b, int $tabs): ?array
    {
        if (! is_array($b) || ! in_array($b['type'] ?? null, self::TYPES, true)) {
            throw new AppContentError($this->where.'unknown block.');
        }
        $type = $b['type'];

        return match ($type) {
            'hero' => ($x = array_filter(['title' => $this->text($b['title'] ?? '', 80), 'text' => $this->text($b['text'] ?? '', 300),
                'image' => $this->image($b['image'] ?? null), 'button' => $this->button($b['button'] ?? null, $tabs)])) ? ['type' => $type] + $x : null,
            'heading' => ($t = $this->text($b['text'] ?? '', 120)) !== '' ? ['type' => $type, 'text' => $t, 'align' => ($b['align'] ?? '') === 'center' ? 'center' : 'left'] : null,
            'text' => ($t = $this->text($b['text'] ?? '', 3000, true)) !== '' ? ['type' => $type, 'text' => $t, 'align' => ($b['align'] ?? '') === 'center' ? 'center' : 'left'] : null,
            'image' => ($img = $this->image($b['image'] ?? null)) ? array_filter(['type' => $type, 'image' => $img, 'caption' => $this->text($b['caption'] ?? '', 200), 'alt' => $this->text($b['alt'] ?? '', 150),
                'action' => $this->action($b['action'] ?? null, $tabs), 'round' => ! empty($b['round'])]) : null,
            'button' => ($x = $this->button($b, $tabs)) ? ['type' => $type] + $x + ['style' => ($b['style'] ?? '') === 'outline' ? 'outline' : 'solid'] : null,
            'list' => $this->list($b, $tabs),
            'gallery' => $this->gallery($b),
            'contact' => $this->contact($b),
            'hours' => $this->hours($b),
            'video' => $this->video($b),
            'divider' => ['type' => 'divider'],
            'slider' => ($imgs = $this->images($b['images'] ?? null, 12, 'a slider')) ? ['type' => 'slider', 'images' => $imgs] : null,
            'cards' => $this->cards($b, $tabs),
            'reviews' => $this->reviews($b),
            'faq' => $this->faq($b),
            'social' => $this->social($b),
            'notice' => ($t = $this->text($b['text'] ?? '', 300, true)) !== '' ? ['type' => 'notice', 'text' => $t, 'tone' => in_array($b['tone'] ?? '', ['info', 'success', 'warning'], true) ? $b['tone'] : 'info'] : null,
            'products' => $this->products($b),
            'booking' => $this->booking($b),
            'loyalty' => $this->loyalty($b),
            'spacer' => ['type' => 'spacer', 'size' => in_array($b['size'] ?? '', ['small', 'medium', 'large'], true) ? $b['size'] : 'medium'],
        };
    }

    /** A label and an action, both needed. */
    private function button(mixed $b, int $tabs): ?array
    {
        if (! is_array($b)) {
            return null;
        }
        $label = $this->text($b['label'] ?? '', 40);
        $action = $this->action($b['action'] ?? null, $tabs);
        if ($label === '' && ! $action) {
            return null;
        }
        if ($label === '' || ! $action) {
            throw new AppContentError($this->where.'a button needs both a label and what it does.');
        }

        return ['label' => $label, 'action' => $action];
    }

    private function list(array $b, int $tabs): ?array
    {
        $items = is_array($b['items'] ?? null) ? array_values($b['items']) : [];
        if (count($items) > 50) {
            throw new AppContentError($this->where.'a list can have up to 50 items.');
        }
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $x = array_filter(['title' => $this->text($item['title'] ?? '', 80), 'text' => $this->text($item['text'] ?? '', 400, true),
                'price' => $this->text($item['price'] ?? '', 20), 'image' => $this->image($item['image'] ?? null), 'action' => $this->action($item['action'] ?? null, $tabs)]);
            if ($x) {
                $clean[] = $x;
            }
        }
        $title = $this->text($b['title'] ?? '', 80);

        return $clean ? array_filter(['type' => 'list', 'title' => $title, 'items' => $clean]) : null;
    }

    private function gallery(array $b): ?array
    {
        $clean = $this->images($b['images'] ?? null, 24, 'a gallery');

        return $clean ? ['type' => 'gallery', 'images' => $clean, 'columns' => in_array((int) ($b['columns'] ?? 2), [2, 3], true) ? (int) $b['columns'] : 2] : null;
    }

    /** A list of pictures (gallery, slider), up to $max. */
    private function images(mixed $images, int $max, string $what): array
    {
        $images = is_array($images) ? array_values($images) : [];
        if (count($images) > $max) {
            throw new AppContentError($this->where.$what.' can have up to '.$max.' pictures.');
        }

        return array_values(array_filter(array_map(fn ($i) => $this->image($i), $images)));
    }

    /** Products people can add to an order (sent to the owner on WhatsApp or by email: AppBuilder settings "shop"). */
    private function products(array $b): ?array
    {
        $items = is_array($b['items'] ?? null) ? array_values($b['items']) : [];
        if (count($items) > 60) {
            throw new AppContentError($this->where.'a products block can have up to 60 products.');
        }
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item) || ($name = $this->text($item['name'] ?? '', 80)) === '') {
                continue;
            }
            $raw = str_replace(',', '.', trim((string) (is_scalar($item['price'] ?? null) ? $item['price'] : '')));
            if ($raw !== '' && (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > 10_000_000)) {
                throw new AppContentError($this->where.'“'.$name.'” needs a price as a number, e.g. 12.50 (or leave it empty).');
            }
            $clean[] = array_filter(['name' => $name, 'text' => $this->text($item['text'] ?? '', 400, true), 'category' => $this->text($item['category'] ?? '', 40),
                'price' => $raw === '' ? null : round((float) $raw, 2), 'image' => $this->image($item['image'] ?? null)], fn ($v) => $v !== null && $v !== '');
        }

        return $clean ? array_filter(['type' => 'products', 'title' => $this->text($b['title'] ?? '', 80), 'items' => $clean]) : null;
    }

    /**
     * A loyalty card kept on the customer's phone: staff type a PIN on it to add a stamp or give the reward. Only a
     * hash of the PIN is kept (salted with the card's id), never the PIN itself, and it isn't published: phones
     * send the PIN to Api\AppLoyaltyController, which checks it and limits wrong tries.
     */
    private function loyalty(array $b): array
    {
        $card = is_string($b['card'] ?? null) && preg_match('/^[a-z0-9]{8,16}$/', $b['card']) ? $b['card'] : throw new AppContentError($this->where.'please add the loyalty card again.');
        $pin = is_scalar($b['pin'] ?? null) ? trim((string) $b['pin']) : '';
        if ($pin !== '') {
            if (! preg_match('/^\d{4,8}$/', $pin)) {
                throw new AppContentError($this->where.'the staff PIN must be 4 to 8 digits.');
            }
            $hash = hash('sha256', $card.':'.$pin);
        } else {
            $hash = is_string($b['pinHash'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $b['pinHash']) ? $b['pinHash'] : throw new AppContentError($this->where.'please set a staff PIN for the loyalty card.');
        }
        $stamps = (int) ($b['stamps'] ?? 10);
        if ($stamps < 4 || $stamps > 12) {
            throw new AppContentError($this->where.'a loyalty card needs 4 to 12 stamps.');
        }
        $reward = $this->text($b['reward'] ?? '', 60);
        if ($reward === '') {
            throw new AppContentError($this->where.'say what the reward is, e.g. “Free coffee”.');
        }

        return array_filter(['type' => 'loyalty', 'card' => $card, 'title' => $this->text($b['title'] ?? '', 80), 'reward' => $reward,
            'text' => $this->text($b['text'] ?? '', 200), 'stamps' => $stamps, 'pinHash' => $hash], fn ($v) => $v !== '');
    }

    /**
     * Appointment requests: services, the days and hours people can pick from and how long a slot is. The request is
     * sent to the owner on WhatsApp or by email (AppBuilder settings "shop"), who confirms it.
     */
    private function booking(array $b): array
    {
        $services = [];
        foreach (array_slice(is_array($b['services'] ?? null) ? array_values($b['services']) : [], 0, 21) as $sv) {
            if (is_array($sv) && ($name = $this->text($sv['name'] ?? '', 60)) !== '') {
                $services[] = array_filter(['name' => $name, 'info' => $this->text($sv['info'] ?? '', 40)]);
            }
        }
        if (count($services) > 20) {
            throw new AppContentError($this->where.'a booking block can have up to 20 services.');
        }
        $days = array_values(array_unique(array_filter(is_array($b['days'] ?? null) ? $b['days'] : [], fn ($d) => is_int($d) || ctype_digit((string) $d))));
        $days = array_values(array_filter(array_map('intval', $days), fn ($d) => $d >= 0 && $d <= 6));
        sort($days);
        $time = fn ($v) => is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
        $from = $time($b['from'] ?? null) ?? throw new AppContentError($this->where.'please set the time bookings start.');
        $to = $time($b['to'] ?? null) ?? throw new AppContentError($this->where.'please set the time bookings end.');
        $slot = (int) ($b['slot'] ?? 30);
        if (! $days) {
            throw new AppContentError($this->where.'pick at least one day people can book.');
        }
        if ($from >= $to) {
            throw new AppContentError($this->where.'the last booking time must be after the first.');
        }
        if (! in_array($slot, [15, 20, 30, 45, 60, 90, 120], true)) {
            throw new AppContentError($this->where.'please pick how long each appointment is.');
        }

        return array_filter(['type' => 'booking', 'title' => $this->text($b['title'] ?? '', 80), 'text' => $this->text($b['text'] ?? '', 300, true),
            'services' => $services, 'days' => $days, 'from' => $from, 'to' => $to, 'slot' => $slot, 'ahead' => max(1, min(90, (int) ($b['ahead'] ?? 30)))], fn ($v) => $v !== '' && $v !== []);
    }

    /** Small cards in a grid: an icon, a title and a line of text (features, services, reasons to choose you). */
    private function cards(array $b, int $tabs): ?array
    {
        $items = is_array($b['items'] ?? null) ? array_values($b['items']) : [];
        if (count($items) > 12) {
            throw new AppContentError($this->where.'cards can have up to 12 items.');
        }
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $x = array_filter(['title' => $this->text($item['title'] ?? '', 60), 'text' => $this->text($item['text'] ?? '', 200, true), 'action' => $this->action($item['action'] ?? null, $tabs)]);
            if (isset($x['title']) || isset($x['text'])) {
                $clean[] = ['icon' => array_key_exists(is_string($item['icon'] ?? null) ? $item['icon'] : '', AppBuilder::icons()) ? $item['icon'] : 'star'] + $x;
            }
        }

        return $clean ? array_filter(['type' => 'cards', 'title' => $this->text($b['title'] ?? '', 80), 'columns' => (int) ($b['columns'] ?? 2) === 1 ? 1 : 2, 'items' => $clean]) : null;
    }

    private function reviews(array $b): ?array
    {
        $items = is_array($b['items'] ?? null) ? array_values($b['items']) : [];
        if (count($items) > 20) {
            throw new AppContentError($this->where.'reviews can have up to 20 items.');
        }
        $clean = [];
        foreach ($items as $item) {
            if (is_array($item) && ($text = $this->text($item['text'] ?? '', 500, true)) !== '') {
                $stars = filter_var($item['stars'] ?? 5, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
                $clean[] = ['text' => $text, 'name' => $this->text($item['name'] ?? '', 60), 'stars' => $stars === false ? 5 : $stars];
            }
        }

        return $clean ? array_filter(['type' => 'reviews', 'title' => $this->text($b['title'] ?? '', 80), 'items' => $clean]) : null;
    }

    /** Questions that open to show their answer. */
    private function faq(array $b): ?array
    {
        $items = is_array($b['items'] ?? null) ? array_values($b['items']) : [];
        if (count($items) > 30) {
            throw new AppContentError($this->where.'an FAQ can have up to 30 questions.');
        }
        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = $this->text($item['q'] ?? '', 160);
            $a = $this->text($item['a'] ?? '', 1500, true);
            if ($q !== '' && $a !== '') {
                $clean[] = ['q' => $q, 'a' => $a];
            } elseif ($q !== '' || $a !== '') {
                throw new AppContentError($this->where.'each question needs an answer.');
            }
        }

        return $clean ? array_filter(['type' => 'faq', 'title' => $this->text($b['title'] ?? '', 80), 'items' => $clean]) : null;
    }

    private function social(array $b): ?array
    {
        $links = [];
        foreach (self::SOCIAL as $key) {
            if (($v = $this->text($b['links'][$key] ?? '', 300)) !== '') {
                $links[$key] = AppBuilder::httpsUrl($v) ?? throw new AppContentError($this->where.'social links must be full addresses starting with https://');
            }
        }

        return $links ? array_filter(['type' => 'social', 'title' => $this->text($b['title'] ?? '', 80), 'links' => $links]) : null;
    }

    private function contact(array $b): ?array
    {
        $x = [];
        if (($v = $this->text($b['phone'] ?? '', 25)) !== '') {
            $x['phone'] = self::phone($v) ?? throw new AppContentError($this->where.'the phone number doesn’t look right.');
        }
        if (($v = $this->text($b['whatsapp'] ?? '', 25)) !== '') {
            $x['whatsapp'] = self::whatsapp($v) ?? throw new AppContentError($this->where.'the WhatsApp number should be the full number with country code, e.g. +44 7700 900123.');
        }
        if (($v = $this->text($b['email'] ?? '', 120)) !== '') {
            $x['email'] = filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : throw new AppContentError($this->where.'the email address doesn’t look right.');
        }
        if (($v = $this->text($b['address'] ?? '', 200, true)) !== '') {
            $x['address'] = $v;
        }
        if (($v = $this->text($b['website'] ?? '', 500)) !== '') {
            $x['website'] = AppBuilder::httpsUrl($v) ?? throw new AppContentError($this->where.'the website should start with https://');
        }

        return $x ? ['type' => 'contact', 'title' => $this->text($b['title'] ?? '', 80)] + $x : null;
    }

    private function hours(array $b): ?array
    {
        $rows = is_array($b['rows'] ?? null) ? array_values($b['rows']) : [];
        if (count($rows) > 14) {
            throw new AppContentError($this->where.'opening hours can have up to 14 rows.');
        }
        $clean = [];
        foreach ($rows as $r) {
            if (is_array($r) && (($day = $this->text($r['day'] ?? '', 30)) !== '' || ($r['time'] ?? '') !== '')) {
                $clean[] = ['day' => $day ?? '', 'time' => $this->text($r['time'] ?? '', 60)];
            }
        }

        return $clean ? ['type' => 'hours', 'title' => $this->text($b['title'] ?? '', 80), 'rows' => $clean] : null;
    }

    private function video(array $b): ?array
    {
        $url = $this->text($b['url'] ?? '', 300);
        if ($url === '') {
            return null;
        }
        $id = self::youtubeId($url) ?? throw new AppContentError($this->where.'please paste a YouTube video link.');

        return ['type' => 'video', 'id' => $id, 'title' => $this->text($b['title'] ?? '', 120)];
    }

    /**
     * What tapping does: open a website, call, email, WhatsApp, a map, or another tab.
     *
     * @return array{kind: string, value: string|int}|null
     */
    private function action(mixed $a, int $tabs): ?array
    {
        if (! is_array($a) || ! in_array($a['kind'] ?? null, self::ACTIONS, true)) {
            return null;
        }
        $kind = $a['kind'];
        if ($kind === 'tab') {
            $i = filter_var($a['value'] ?? null, FILTER_VALIDATE_INT);

            return $i !== false && $i >= 0 && $i < $tabs ? ['kind' => 'tab', 'value' => $i] : throw new AppContentError($this->where.'that button opens a tab that isn’t there.');
        }
        $v = $this->text(is_scalar($a['value'] ?? null) ? (string) $a['value'] : '', 500);
        if ($v === '') {
            return null;
        }
        $value = match ($kind) {
            'url' => AppBuilder::httpsUrl($v) ?? throw new AppContentError($this->where.'links must start with https://'),
            'tel' => self::phone($v) ?? throw new AppContentError($this->where.'the phone number doesn’t look right.'),
            'whatsapp' => self::whatsapp($v) ?? throw new AppContentError($this->where.'the WhatsApp number should be the full number with country code.'),
            'email' => filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : throw new AppContentError($this->where.'the email address doesn’t look right.'),
            'map' => mb_substr($v, 0, 200),
        };

        return ['kind' => $kind, 'value' => $value];
    }

    /**
     * A picture: a new one from the builder (data: URL) is stored under a name made from its bytes; one saved
     * before must already be in this app's folder. Width and height always come from the file itself.
     *
     * @return array{src: string, w: int, h: int}|null
     */
    private function image(mixed $in): ?array
    {
        $src = is_array($in) ? ($in['src'] ?? null) : null;
        if (! is_string($src) || $src === '') {
            return null;
        }
        if (str_starts_with($src, 'data:')) {
            if (! preg_match('#^data:image/(jpeg|png|webp);base64,#', $src, $m) || strlen($src) > self::MAX_IMAGE_BYTES * 1.4) {
                throw new AppContentError($this->where.'pictures must be JPG, PNG or WebP, up to 2.5 MB.');
            }
            $bytes = base64_decode(substr($src, strlen($m[0])), true);
            $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            $types = ['jpeg' => [IMAGETYPE_JPEG, 'jpg'], 'png' => [IMAGETYPE_PNG, 'png'], 'webp' => [IMAGETYPE_WEBP, 'webp']];
            if (! $size || $size[2] !== $types[$m[1]][0] || $size[0] < 16 || $size[1] < 16 || $size[0] > 4000 || $size[1] > 4000 || strlen($bytes) > self::MAX_IMAGE_BYTES) {
                throw new AppContentError($this->where.'we couldn’t read that picture. Please add it again.');
            }
            $name = substr(hash('sha256', $bytes), 0, 20).'.'.$types[$m[1]][1];
            if (! isset($this->new[$name])) {
                $this->newBytes += strlen($bytes);
                if ($this->newBytes > self::MAX_NEW_BYTES) {
                    throw new AppContentError('You added a lot of pictures at once. Please save, then add the rest.');
                }
                $this->new[$name] = $bytes;
            }
            $this->used[$name] = true;

            return ['src' => $name, 'w' => $size[0], 'h' => $size[1]];
        }
        if (! preg_match('/^[a-f0-9]{20}\.(jpg|png|webp)$/', $src)) {
            throw new AppContentError($this->where.'please add that picture again.');
        }
        $bytes = $this->new[$src] ?? null;
        $size = $bytes !== null ? @getimagesizefromstring($bytes) : ($this->app ? @getimagesize(self::dir($this->app).'/img/'.$src) : false);
        if (! $size) {
            throw new AppContentError($this->where.'a picture is missing. Please add it again.');
        }
        $this->used[$src] = true;

        return ['src' => $src, 'w' => $size[0], 'h' => $size[1]];
    }

    private function text(mixed $v, int $max, bool $lines = false): string
    {
        $s = self::str($v, $lines);
        if (mb_strlen($s) > $max) {
            throw new AppContentError($this->where.'that text is too long (up to '.$max.' characters).');
        }

        return $s;
    }

    /** Text without control characters (new lines kept when $lines), trimmed. */
    public static function str(mixed $v, bool $lines = false): string
    {
        if (! is_string($v)) {
            return '';
        }
        $v = str_replace("\r\n", "\n", $v);
        $v = preg_replace($lines ? '/[\x00-\x09\x0b-\x1f\x7f]/u' : '/[\x00-\x1f\x7f]/u', '', $v) ?? '';

        return trim($lines ? preg_replace("/\n{3,}/", "\n\n", $v) : $v);
    }

    public static function phone(string $v): ?string
    {
        return preg_match('/^\+?[0-9][0-9 ()\-.]{2,22}$/', $v) ? $v : null;
    }

    /** WhatsApp number: digits only, with country code (wa.me/{digits}). */
    public static function whatsapp(string $v): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $v);

        return preg_match('/^\+?[0-9 ()\-.]+$/', $v) && strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function youtubeId(string $url): ?string
    {
        $p = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $p['host'] ?? ''));
        $id = match ($host) {
            'youtu.be' => trim($p['path'] ?? '', '/'),
            'youtube.com', 'youtube-nocookie.com', 'music.youtube.com' => preg_match('#^/(?:shorts|embed|live|v)/([^/]+)#', $p['path'] ?? '', $m) ? $m[1] : (function () use ($p) {
                parse_str($p['query'] ?? '', $q);

                return is_string($q['v'] ?? null) ? $q['v'] : '';
            })(),
            default => '',
        };

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    /* ---------- the static files ---------- */

    public static function root(): string
    {
        return rtrim((string) (config('builder.apps.public_dir') ?: public_path('app-data')), '/');
    }

    public static function dir(BuilderApp $app): string
    {
        return self::root().'/'.$app->id;
    }

    /** The folder's web address, ending in "/". */
    public static function baseUrl(BuilderApp $app): string
    {
        return rtrim(config('builder.apps.public_url') ?: rtrim((string) config('app.url'), '/').'/app-data', '/').'/'.$app->id.'/';
    }

    /** What the app downloads: everything that can change without a new build. */
    public static function document(BuilderApp $app): array
    {
        $s = $app->settings;
        if (! $app->isLive()) {
            // Taken down: phones that have the app show this instead (it can't be changed until it's restored)
            return ['v' => 1, 'rev' => (int) floor(microtime(true) * 1000), 'base' => self::baseUrl($app), 'colors' => ['primary' => '#64748b', 'bar' => '#ffffff'],
                'statusBar' => 'dark', 'externalLinks' => 'browser', 'hosts' => [], 'offline' => $s['offline'], 'menu' => 'hide', 'report' => $app->reportUrl(),
                'tabs' => [['label' => 'Unavailable', 'icon' => 'info', 'type' => 'screen', 'bar' => true, 'menu' => false,
                    'blocks' => [['type' => 'notice', 'text' => 'This app is no longer available.', 'tone' => 'warning']]]]];
        }

        // Pages of a website show only once the owner has proved the site is theirs (see AppBuilder::allowed)
        $webOk = AppBuilder::allowed($app);
        // the PIN's hash stays here: phones ask the website to check a PIN (Api\AppLoyaltyController), so it can't be guessed offline
        $noPins = function (array $t) {
            foreach ($t['blocks'] ?? [] as $i => $b) {
                if (($b['type'] ?? '') === 'loyalty') {
                    unset($t['blocks'][$i]['pinHash']);
                }
            }

            return $t;
        };
        $tabs = fn (array $tabs) => array_map($noPins, $webOk ? $tabs : array_map(fn ($t) => ($t['type'] ?? '') !== 'web' ? $t
            : ['label' => $t['label'], 'icon' => $t['icon'], 'type' => 'screen', 'bar' => $t['bar'] ?? true, 'menu' => $t['menu'] ?? true,
                'blocks' => [['type' => 'notice', 'text' => 'This page will appear soon.', 'tone' => 'info']]], $tabs));

        return [
            'v' => 1,
            'rev' => (int) ($app->content['rev'] ?? 0),
            'base' => self::baseUrl($app),
            'colors' => ['primary' => $s['colors']['primary'], 'bar' => $s['colors']['bar']],
            'statusBar' => $s['status_bar'],
            'externalLinks' => $s['external_links'],
            'menu' => ($s['menu'] ?? '') === 'hide' ? 'hide' : 'show',
            'report' => $app->reportUrl(),
            'shop' => self::shopForPhones($app),
            'style' => ($s['style'] ?? []) + ['theme' => 'light', 'corners' => 'rounded', 'font' => 'modern', 'nav' => 'floating', 'motion' => 'on'],
            'hosts' => $app->url && $webOk ? [$app->host()] : [],
            'offline' => $s['offline'],
            'tabs' => $tabs($app->content['tabs'] ?? []),
            'lang' => $s['lang'] ?? 'en',
            'translations' => (object) array_map(fn ($tr) => ['tabs' => $tabs($tr['tabs'] ?? [])] + $tr, $app->content['translations'] ?? []),
        ];
    }

    /**
     * The order settings phones get: where orders go, and the ways to pay. Online card payment is there only when
     * owlvix offers that mode (Stripe::available) and the owner has connected a Stripe account for it.
     */
    private static function shopForPhones(BuilderApp $app): ?array
    {
        $shop = $app->settings['shop'] ?? null;
        if (! is_array($shop)) {
            return null;
        }
        $pay = $shop['pay'] ?? [];
        $mode = ($pay['mode'] ?? 'test') === 'live' ? 'live' : 'test';
        $online = ! empty($pay['online']) && Stripe::available($mode) && Stripe::account($app, $mode);
        $shop['pay'] = ['cash' => ! empty($pay['cash']), 'card' => ! empty($pay['card']), 'bank' => ! empty($pay['bank']),
            'bank_details' => ! empty($pay['bank']) ? (string) ($pay['bank_details'] ?? '') : '',
            'online' => $online ? ['test' => $mode === 'test', 'url' => rtrim(config('builder.apps.callback_url') ?: config('app.url'), '/').'/api/app-pay/'.$app->id] : null];

        return $shop;
    }

    /**
     * Writes content.json and the new pictures, and removes pictures no longer used. content.json is written to a
     * temporary file and renamed, so a phone never reads half a file.
     *
     * @param  array<string, string>  $images  new pictures: file name => bytes
     */
    public static function publish(BuilderApp $app, array $images = []): void
    {
        $dir = self::dir($app);
        File::ensureDirectoryExists($dir.'/img');
        File::put(self::root().'/.htaccess', self::htaccess());
        foreach ($images as $name => $bytes) {
            if (preg_match('/^[a-f0-9]{20}\.(jpg|png|webp)$/', $name) && ! is_file("$dir/img/$name")) {
                File::put("$dir/img/$name", $bytes);
            }
        }
        $tmp = $dir.'/.content-'.Str::random(8).'.json';
        File::put($tmp, json_encode(self::document($app), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        rename($tmp, $dir.'/content.json');

        $used = [];
        $content = $app->content ?? [];
        array_walk_recursive($content, function ($v, $k) use (&$used) {
            if ($k === 'src' && is_string($v)) {
                $used[$v] = true;
            }
        });
        foreach (File::files($dir.'/img') as $file) {
            if (! isset($used[$file->getFilename()])) {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * Takes an app's pictures and files offline (moved to private storage, so a restore can bring them back) and
     * publishes the "unavailable" content; or, after a restore, the other way round.
     */
    public static function setLive(BuilderApp $app, bool $live): void
    {
        $public = self::dir($app);
        $private = BuilderApp::disk()->path($app->dir().'/offline');
        [$from, $to] = $live ? [$private, $public] : [$public, $private];
        if ($live) {
            // the take-down notice went out with a newer rev; phones ignore anything older, so restore with a newer one
            $app->forceFill(['content' => ['rev' => max((int) floor(microtime(true) * 1000), (int) ($app->content['rev'] ?? 0) + 1)] + ($app->content ?? [])])->save();
        }
        foreach (['img', 'builds'] as $folder) {
            if (is_dir("$from/$folder")) {
                File::ensureDirectoryExists($to);
                File::deleteDirectory("$to/$folder");
                File::moveDirectory("$from/$folder", "$to/$folder");
            }
        }
        self::publish($app);
    }

    /** Publishes the app again with a newer rev (phones take it), e.g. after settings outside the editor changed. */
    public static function republish(BuilderApp $app): void
    {
        if (! $app->isLive()) {
            return;
        }
        $app->forceFill(['content' => ['rev' => max((int) floor(microtime(true) * 1000), (int) ($app->content['rev'] ?? 0) + 1)] + ($app->content ?? [])])->save();
        self::publish($app);
    }

    public static function remove(BuilderApp $app): void
    {
        if (preg_match('/^[a-z0-9]{16}$/', (string) $app->id)) {
            File::deleteDirectory(self::dir($app));
        }
    }

    /** Folder rules for Apache: never run code, no listings, content.json always checked again, pictures cached. */
    public static function htaccess(): string
    {
        return "# Written by owlvix.com (App\\Support\\AppContent::htaccess): mobile app builder content and app files.\n"
            ."# Rewritten on every save: edit the code, not this file.\n"
            ."Options -Indexes\n\n"
            ."<FilesMatch \"\\.(php\\d?|pht|phps|phtml|phar|pl|py|cgi|sh|s?html?|svg|js)$\">\n    Require all denied\n</FilesMatch>\n"
            ."RemoveHandler .php .php5 .php7 .php8 .pht .phtml .phar\n"
            ."<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
            ."<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule (^|/)\\. - [R=404,L]\n</IfModule>\n\n"
            ."AddType application/json .json\nAddType image/webp .webp\n"
            ."AddType application/vnd.android.package-archive .apk\nAddType application/octet-stream .aab\n\n"
            ."<IfModule mod_headers.c>\n"
            ."    Header always set X-Content-Type-Options \"nosniff\"\n"
            ."    Header always set X-Robots-Tag \"noindex\"\n"
            ."    <FilesMatch \"\\.json$\">\n        Header set Cache-Control \"no-cache\"\n    </FilesMatch>\n"
            ."    <FilesMatch \"\\.(jpg|png|webp)$\">\n        Header set Cache-Control \"public, max-age=31536000, immutable\"\n    </FilesMatch>\n"
            ."    <FilesMatch \"\\.(apk|aab)$\">\n        Header set Content-Disposition \"attachment\"\n        Header set Cache-Control \"private, max-age=86400\"\n    </FilesMatch>\n"
            ."</IfModule>\n";
    }
}
