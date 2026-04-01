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

// Cookie consent
const consent = useCookie<boolean | null>('aktiv_consent', {
  maxAge: 60 * 60 * 24 * 365,
  sameSite: 'lax'
});
const showConsent = ref(false);

function handleAccept() {
  consent.value = true;
  clarityConsent(true);
  showConsent.value = false;
}

function handleDecline() {
  consent.value = false;
  clarityConsent(false);
  showConsent.value = false;
}

onMounted(() => {
  if (consent.value === undefined || consent.value === null) {
    showConsent.value = true;
  } else {
    clarityConsent(consent.value === true);
  }
});

// Rehydrate auth state from cookie on every page load
await init();

// Post-booking review popup state
const reviewQueue = ref<UserBooking[]>([]);
const reviewPopupOpen = ref(false);
const pendingReviewBooking = computed(() => reviewQueue.value[0] ?? null);

function advanceQueue() {
  reviewQueue.value.shift();
  if (!reviewQueue.value.length) reviewPopupOpen.value = false;
}

async function checkPendingReview(testBookingId?: number) {
  try {
    const res = await fetchPendingReview(testBookingId);
    if (res.bookings?.length) {
      reviewQueue.value = res.bookings;
      setTimeout(() => {
        reviewPopupOpen.value = true;
      }, 1500);
    }
  } catch {
    // Silently ignore — non-critical
  }
}

useFaviconBadge(() => notificationStore.unreadCount);

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
    <NuxtLoadingIndicator />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>

    <!-- Cookie consent -->
    <AppCookieConsentModal
      v-if="showConsent"
      @accept="handleAccept"
      @decline="handleDecline"
    />

    <!-- Post-booking review popup -->
    <AppBookingReviewPopup
      v-if="pendingReviewBooking"
      v-model:open="reviewPopupOpen"
      :booking="pendingReviewBooking"
      @submitted="advanceQueue"
      @skipped="advanceQueue"
    />
  </UApp>
</template>
