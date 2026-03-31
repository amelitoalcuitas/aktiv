import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware(async () => {
  const authStore = useAuthStore();

  if (!authStore.isAuthenticated) {
    return navigateTo('/auth/login');
  }

  // Fetch user if token exists but user data isn't loaded yet
  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (!authStore.isOwner) {
    return navigateTo('/');
  }
});
