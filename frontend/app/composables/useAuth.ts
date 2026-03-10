import { useAuthStore } from '~/stores/auth';
import type { User } from '~/types/user';

export function useAuth() {
  const authStore = useAuthStore();
  const router = useRouter();

  const isAuthenticated = computed(() => authStore.isAuthenticated);
  const user = computed(() => authStore.user);

  async function login(email: string, password: string): Promise<void> {
    const res = await $fetch<{ user: User; token: string }>('/api/auth/login', {
      method: 'POST',
      body: { email, password }
    });
    authStore.setToken(res.token);
    authStore.setUser(res.user);
  }

  async function register(
    name: string,
    email: string,
    password: string,
    password_confirmation: string
  ): Promise<void> {
    const res = await $fetch<{ user: User; token: string }>(
      '/api/auth/register',
      {
        method: 'POST',
        body: { name, email, password, password_confirmation }
      }
    );
    authStore.setToken(res.token);
    authStore.setUser(res.user);
  }

  async function logout(): Promise<void> {
    try {
      await $fetch('/api/auth/logout', {
        method: 'POST',
        headers: authStore.token
          ? { Authorization: `Bearer ${authStore.token}` }
          : {}
      });
    } catch {
      // ignore server errors on logout
    }
    authStore.logout();
    await router.push('/');
  }

  async function init(): Promise<void> {
    if (authStore.token && !authStore.user) {
      await authStore.fetchUser();
    }
  }

  return { isAuthenticated, user, login, register, logout, init };
}
