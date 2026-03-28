import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware(() => {
  const authStore = useAuthStore();

  if (authStore.user?.role !== 'super_admin') {
    return navigateTo('/');
  }
});
