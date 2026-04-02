import { useAuthStore } from '~/stores/auth';
import type { User } from '~/types/user';
import type { EmailActionCooldown } from '~/composables/useCooldownTimer';

interface EmailActionCooldownResponse {
  cooldown: EmailActionCooldown;
}

interface GoogleRedirectResponse {
  url: string;
}

interface GoogleCompletionResponse {
  user: User;
  token: string;
}

export function useAuth() {
  const authStore = useAuthStore();
  const router = useRouter();
  const config = useRuntimeConfig();

  const isAuthenticated = computed(() => authStore.isAuthenticated);
  const isOwner = computed(() => authStore.isOwner);
  const isSuperAdmin = computed(() => authStore.user?.role === 'super_admin');
  const user = computed(() => authStore.user);

  function apiPath(path: string): string {
    return path.startsWith('/') ? path.slice(1) : path;
  }

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
    country: string,
    province: string,
    city: string,
    password: string,
    password_confirmation: string
  ): Promise<void> {
    const res = await $fetch<{ user: User; token: string }>(
        '/api/auth/register',
      {
        method: 'POST',
        body: {
          first_name,
          last_name,
          email,
          country,
          province,
          city,
          password,
          password_confirmation
        }
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
    await router.replace('/auth/login');
  }

  async function resendVerification(): Promise<EmailActionCooldownResponse> {
    return await $fetch<EmailActionCooldownResponse>('/api/auth/email/resend-verification', {
      method: 'POST',
      headers: authStore.token
        ? { Authorization: `Bearer ${authStore.token}` }
        : {}
    });
  }

  async function resendVerificationStatus(): Promise<EmailActionCooldownResponse> {
    return await $fetch<EmailActionCooldownResponse>('/api/auth/email/resend-verification/status', {
      method: 'GET',
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

  async function setupPassword(token: string, email: string, password: string, password_confirmation: string): Promise<void> {
    await $fetch('/api/auth/account/setup', {
      method: 'POST',
      body: { token, email, password, password_confirmation }
    });
  }

  async function getGoogleRedirectUrl(redirect?: string): Promise<string> {
    const query =
      typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
        ? { redirect }
        : undefined;

    const res = await $fetch<GoogleRedirectResponse>(apiPath('/auth/google/redirect'), {
      baseURL: config.public.apiBase,
      query
    });

    return res.url;
  }

  function completeLogin(nextUser: User, token: string): void {
    authStore.setToken(token);
    authStore.setUser(nextUser);
  }

  async function finalizeGoogleLogin(token: string): Promise<User> {
    authStore.setToken(token);
    await authStore.fetchUser();

    if (!authStore.user) {
      authStore.logout();
      throw new Error('Missing user after Google sign-in.');
    }

    completeLogin(authStore.user, token);

    return authStore.user;
  }

  async function completeGoogleSignup(
    pendingToken: string,
    country: string,
    province: string,
    city: string
  ): Promise<User> {
    const res = await $fetch<GoogleCompletionResponse>(apiPath('/auth/google/complete'), {
      baseURL: config.public.apiBase,
      method: 'POST',
      body: {
        pending_token: pendingToken,
        country,
        province,
        city
      }
    });

    completeLogin(res.user, res.token);

    return res.user;
  }

  async function init(): Promise<void> {
    if (authStore.token && !authStore.user) {
      await authStore.fetchUser();
    }
  }

  return { isAuthenticated, isOwner, isSuperAdmin, user, login, register, logout, init, resendVerification, resendVerificationStatus, forgotPassword, resetPassword, setupPassword, getGoogleRedirectUrl, completeLogin, finalizeGoogleLogin, completeGoogleSignup };
}
