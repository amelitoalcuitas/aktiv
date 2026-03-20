import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig();

  // @ts-expect-error Pusher must be on window for Echo
  window.Pusher = Pusher;

  // Disable Pusher's auto-connect — we connect manually when the user authenticates
  Pusher.logToConsole = false;

  const echo = new Echo({
    broadcaster: 'reverb',
    key: config.public.reverbKey,
    wsHost: config.public.reverbHost,
    wsPort: config.public.reverbPort as number,
    wssPort: config.public.reverbPort as number,
    forceTLS: config.public.reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${config.public.apiBase}/broadcasting/auth`,
    // Disable automatic connection on load
    disableStats: true,
  });

  // Don't connect until explicitly told to
  echo.connector.pusher.connection.disconnect();

  return { provide: { echo } };
});
