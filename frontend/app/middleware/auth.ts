import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore();

  if (to.path.startsWith('/auth/')) {
    if (authStore.isAuthenticated && !authStore.user) {
      await authStore.fetchUser();
    }

    if (authStore.isAuthenticated && authStore.user?.email_verified_at) {
      return navigateTo('/');
    }

    return;
  }

  if (!authStore.isAuthenticated) {
    return navigateTo('/auth/login');
  }

  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (authStore.user && !authStore.user.email_verified_at) {
    return navigateTo('/auth/verify-email');
  }

});
