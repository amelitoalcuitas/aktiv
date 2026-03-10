import { useAuthStore } from '~/stores/auth';

/**
 * Typed $fetch wrapper that automatically:
 * - Sets base URL to /api (client) or the internal Docker URL (SSR)
 * - Attaches Authorization: Bearer <token> when logged in
 * - Throws FetchError on non-2xx responses (native $fetch behaviour)
 */
export function useApi() {
  const authStore = useAuthStore();
  const config = useRuntimeConfig();

  // On the server, use the internal Docker service URL so SSR requests
  // actually reach the Laravel backend instead of looping back to Nuxt.
  const baseURL = import.meta.server ? config.apiBaseInternal : '/api';

  function apiFetch<T>(
    path: string,
    options: Parameters<typeof $fetch>[1] = {}
  ): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers as Record<string, string> | undefined)
    };

    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`;
    }

    return $fetch<T>(path, {
      baseURL,
      ...options,
      headers
    });
  }

  return { apiFetch };
}
