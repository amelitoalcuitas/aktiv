import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore();

  if (!authStore.isAuthenticated) {
    return navigateTo('/auth/login');
  }

  if (to.path.startsWith('/auth/')) return;

  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (authStore.user && !authStore.user.email_verified_at) {
    return navigateTo('/auth/verify-email');
  }
});
