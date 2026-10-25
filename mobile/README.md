# Owlvix mobile app

iOS and Android app for Owlvix, built with Expo (SDK 58) and Expo Router.

- **Customers:** Home (services, process, pricing, FAQ), Work (portfolio), Get a quote, Reviews.
- **Admin** (Admin tab, same login as the website admin): dashboard, messages with email replies,
  traffic, portfolio (with photo upload) and reviews. New enquiries arrive as push alerts.

It talks to the Laravel API in `../routes/api.php`.

## Run it

Needs **Node 22 or newer** (React Native 0.88). With nvm: `nvm use 25`.

```bash
cd mobile
npm install
npx expo start          # then press i (iOS simulator), a (Android) or scan the QR code with Expo Go
```

By default the app uses `https://owlvix.com`. To use a local Laravel server instead:

```bash
php artisan serve --host=0.0.0.0 --port=8000             # in the project root
EXPO_PUBLIC_API_URL=http://<your-mac-ip>:8000 npx expo start
```

## Push alerts for new enquiries

Alerts need an EAS project id and a real phone (not a simulator):

```bash
npx eas-cli@latest login
npx eas-cli@latest init      # adds the project id to app.json
```

After logging in to the Admin tab, the phone asks for permission and registers itself. The server
sends alerts through Expo (`app/Support/Push.php`).

## Build for the App Store and Google Play

```bash
npx eas-cli@latest build --platform all
npx eas-cli@latest submit --platform all
```

You need an Apple Developer account ($99/year) and a Google Play developer account ($25 once).
App ids: `com.owlvix.app` (both stores), set in `app.json`.

## Checks

```bash
npx tsc --noEmit
npx expo lint
```

## Where things are

| Path | What |
| --- | --- |
| `src/app/(tabs)/` | The five tabs. `dashboard.tsx` is the Admin tab (login or dashboard). |
| `src/app/admin/` | Admin screens opened from the dashboard. |
| `src/app/thanks.tsx` | Shown after a quote is sent. |
| `src/lib/content.ts` | Homepage text. Keep in step with `resources/views/home.blade.php`. |
| `src/lib/api.ts` | API calls and errors. |
| `src/lib/auth.tsx` | Admin login, kept in the phone's secure storage. |
| `src/components/ui.tsx` | Buttons, cards, fields, charts. |
| `src/constants/theme.ts` | Colours (same as the website's light and dark themes). |
