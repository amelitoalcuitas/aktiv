import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware(async () => {
  const authStore = useAuthStore();

  if (!authStore.isAuthenticated) {
    return;
  }

  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (!authStore.isAuthenticated) {
    return;
  }

  if (!authStore.user?.email_verified_at) {
    return navigateTo('/auth/verify-email');
  }

  const destination = authStore.user.role === 'super_admin' ? '/panel' : '/dashboard';
  return navigateTo(destination);
});
