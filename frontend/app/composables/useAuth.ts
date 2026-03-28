import { useAuthStore } from '~/stores/auth';
import type { User } from '~/types/user';

export function useAuth() {
  const authStore = useAuthStore();
  const router = useRouter();

  const isAuthenticated = computed(() => authStore.isAuthenticated);
  const isAdmin = computed(() => authStore.isAdmin);
  const isSuperAdmin = computed(() => authStore.user?.role === 'super_admin');
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
    first_name: string,
    last_name: string,
    email: string,
    password: string,
    password_confirmation: string
  ): Promise<void> {
    const res = await $fetch<{ user: User; token: string }>(
      '/api/auth/register',
      {
        method: 'POST',
        body: { first_name, last_name, email, password, password_confirmation }
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

  async function resendVerification(): Promise<void> {
    await $fetch('/api/auth/email/resend-verification', {
      method: 'POST',
      headers: authStore.token
        ? { Authorization: `Bearer ${authStore.token}` }
        : {}
    });
  }

  async function forgotPassword(email: string): Promise<void> {
    await $fetch('/api/auth/password/forgot', { method: 'POST', body: { email } });
  }

  async function resetPassword(token: string, email: string, password: string, password_confirmation: string): Promise<void> {
    await $fetch('/api/auth/password/reset', {
      method: 'POST',
      body: { token, email, password, password_confirmation }
    });
  }

  async function init(): Promise<void> {
    if (authStore.token && !authStore.user) {
      await authStore.fetchUser();
    }
  }

  return { isAuthenticated, isAdmin, isSuperAdmin, user, login, register, logout, init, resendVerification, forgotPassword, resetPassword };
}
