import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig();
  const authStore = useAuthStore();

  // @ts-expect-error Pusher must be on window for Echo
  window.Pusher = Pusher;

  Pusher.logToConsole = false;

  const echo = new Echo({
    broadcaster: 'reverb',
    key: config.public.reverbKey,
    wsHost: config.public.reverbHost,
    wsPort: config.public.reverbPort as number,
    wssPort: config.public.reverbPort as number,
    forceTLS: config.public.reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    // Use a custom authorizer so the Bearer token is always read fresh at auth time.
    // pusher-js 8.x deprecated authEndpoint/auth.headers in favour of channelAuthorization.
    channelAuthorization: {
      endpoint: `${config.public.apiBase}/broadcasting/auth`,
      customHandler: async ({ socketId, channelName }, callback) => {
        try {
          const data = await $fetch<{ auth: string }>(
            `${config.public.apiBase}/broadcasting/auth`,
            {
              method: 'POST',
              body: { socket_id: socketId, channel_name: channelName },
              headers: { Authorization: `Bearer ${authStore.token}` },
            },
          );
          callback(null, data);
        } catch {
          callback(new Error('Channel authorization failed'), { auth: '' });
        }
      },
    },
  });

  // Don't connect until the user is authenticated (triggered from app.vue)
  echo.connector.pusher.connection.disconnect();

  return { provide: { echo } };
});
