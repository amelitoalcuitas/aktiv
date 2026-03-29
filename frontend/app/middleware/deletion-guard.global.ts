import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path === '/auth/restore-account') return;

  const authStore = useAuthStore();

  if (!authStore.isAuthenticated) return;

  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (authStore.user?.deletion_scheduled_at) {
    return navigateTo('/auth/restore-account');
  }
});
