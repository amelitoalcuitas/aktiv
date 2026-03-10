import type { User } from '~/types/user';

export const useAuthStore = defineStore('auth', () => {
  const token = useCookie<string | null>('aktiv_token', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 30, // 30 days
    sameSite: 'lax',
    secure: false // set to true in production
  });

  const user = ref<User | null>(null);

  const isAuthenticated = computed(() => !!token.value);

  function setToken(value: string | null) {
    token.value = value;
  }

  function setUser(value: User | null) {
    user.value = value;
  }

  async function fetchUser() {
    if (!token.value) return;
    const config = useRuntimeConfig();
    // On the server use the internal Docker URL; on the client use relative /api
    const baseURL = import.meta.server ? config.apiBaseInternal : '/api';
    try {
      const res = await $fetch<{ user: User }>('/auth/me', {
        baseURL,
        headers: { Authorization: `Bearer ${token.value}` }
      });
      user.value = res.user;
    } catch (e: unknown) {
      // Only invalidate the session on a real 401 — not on SSR network errors
      const status = (e as { response?: { status?: number } })?.response
        ?.status;
      if (status === 401) {
        token.value = null;
        user.value = null;
      }
    }
  }

  function logout() {
    token.value = null;
    user.value = null;
  }

  return { token, user, isAuthenticated, setToken, setUser, fetchUser, logout };
});
