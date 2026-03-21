<script setup lang="ts">
import { useAuth } from '~/composables/useAuth';
import { useNotificationStore } from '~/stores/notifications';
import { useUserBookingStore } from '~/stores/booking';
import type { UserBooking } from '~/types/booking';

const { init } = useAuth();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const userBookingStore = useUserBookingStore();
const toaster = { position: 'bottom-left' };
const { fetchPendingReview } = useHubs();

// Rehydrate auth state from cookie on every page load
await init();

// Post-booking review popup state
const pendingReviewBooking = ref<UserBooking | null>(null);
const reviewPopupOpen = ref(false);

async function checkPendingReview(testBookingId?: number) {
  try {
    const res = await fetchPendingReview(testBookingId);
    if (res.booking) {
      const b = res.booking as UserBooking;
      // Don't re-show if already dismissed in this session
      const alreadyDismissed = (() => {
        try { return !!localStorage.getItem(`reviewed_booking_${b.id}`); } catch { return false; }
      })();
      if (!alreadyDismissed) {
        pendingReviewBooking.value = b;
        reviewPopupOpen.value = true;
      }
    }
  } catch {
    // Silently ignore — non-critical
  }
}

// Subscribe to WebSocket notifications when authenticated
watch(
  () => authStore.user,
  (user) => {
    if (user) {
      notificationStore.fetchInitial();
      notificationStore.subscribe(user.id);
      userBookingStore.fetchInitial();
      userBookingStore.subscribe(user.id);
      checkPendingReview();
    } else {
      notificationStore.unsubscribe();
      userBookingStore.unsubscribe();
    }
  },
  { immediate: true }
);

// Dev testing shortcut: ?test_review=<bookingId> forces the popup
// Usage: http://localhost:8080/?test_review=123
if (import.meta.client) {
  const route = useRoute();
  const testId = Number(route.query.test_review);
  if (testId && authStore.isAuthenticated) {
    checkPendingReview(testId);
  }
}
</script>

<template>
  <UApp :toaster="toaster">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>

    <!-- Post-booking review popup -->
    <AppBookingReviewPopup
      v-if="pendingReviewBooking"
      v-model:open="reviewPopupOpen"
      :booking="pendingReviewBooking"
      @submitted="pendingReviewBooking = null"
    />
  </UApp>
</template>
