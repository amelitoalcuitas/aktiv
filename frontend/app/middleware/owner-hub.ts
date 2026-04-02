import { useAuthStore } from '~/stores/auth';
import { useHubStore } from '~/stores/hub';

export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore();
  const hubStore = useHubStore();

  if (!authStore.isAuthenticated) {
    return navigateTo('/auth/login');
  }

  if (!authStore.user) {
    await authStore.fetchUser();
  }

  if (!authStore.isOwner) {
    return navigateTo('/bookings');
  }

  if (!hubStore.initialized) {
    await hubStore.fetchMyHubs();
  }

  const hubId = Array.isArray(to.params.id) ? to.params.id[0] : to.params.id;
  const ownsHub = hubStore.myHubs.some((hub) => hub.id === String(hubId));

  if (!ownsHub) {
    return navigateTo('/bookings');
  }
});
