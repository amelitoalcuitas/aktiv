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

  const baseURL = config.public.apiBase;

  function normalizePath(path: string): string {
    return path.startsWith('/') ? path.slice(1) : path;
  }

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

    return $fetch<T>(normalizePath(path), {
      baseURL,
      ...options,
      headers
    });
  }

  return { apiFetch };
}
