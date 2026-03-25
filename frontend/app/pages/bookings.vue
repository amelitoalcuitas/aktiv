<script setup lang="ts">
import type {
  UserBooking,
  BookingStatus,
  CalendarBooking
} from '~/types/booking';

definePageMeta({ middleware: ['auth'] });

useHead({ title: 'My Bookings · Aktiv' });

const { fetchMyBookings, cancelMyBooking, findBookingPage } = useBooking();
const bookingStore = useUserBookingStore();
const authStore = useAuthStore();
const toast = useToast();
const route = useRoute();
const router = useRouter();

// ── State ─────────────────────────────────────────────────────
const bookings = ref<UserBooking[]>([]);
const paginationMeta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);

const selectedStatus = ref<string>((route.query.status as string) ?? '');
const currentPage = ref(Number(route.query.page) || 1);

const highlightedId = ref<string | null>(null);

const cancelTarget = ref<UserBooking | null>(null);
const cancelConfirmOpen = ref(false);
const cancelling = ref(false);

const receiptTarget = ref<UserBooking | null>(null);
const receiptOpen = ref(false);

// ── Status filter options ─────────────────────────────────────
const statusOptions = [
  { label: 'All', value: '' },
  { label: 'Pending Payment', value: 'pending_payment' },
  { label: 'Payment Sent', value: 'payment_sent' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Completed', value: 'completed' }
];

// ── Status display helpers ────────────────────────────────────
type DisplayStatus = BookingStatus | 'expired';

const statusConfig: Record<
  DisplayStatus,
  { label: string; color: 'warning' | 'info' | 'success' | 'error' | 'neutral' }
> = {
  pending_payment: { label: 'Pending Payment', color: 'warning' },
  payment_sent: { label: 'Payment Sent', color: 'info' },
  confirmed: { label: 'Confirmed', color: 'success' },
  cancelled: { label: 'Cancelled', color: 'error' },
  completed: { label: 'Completed', color: 'neutral' },
  expired: { label: 'Expired', color: 'neutral' }
};

function effectiveStatus(booking: UserBooking): DisplayStatus {
  if (booking.status === 'cancelled' && booking.cancelled_by === 'system')
    return 'expired';
  return booking.status;
}

// ── Load bookings ─────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const result = await fetchMyBookings({
      status: selectedStatus.value || undefined,
      page: currentPage.value
    });
    bookings.value = result.data;
    paginationMeta.value = result.meta;
  } catch {
    toast.add({ title: 'Failed to load bookings', color: 'error' });
  } finally {
    loading.value = false;
  }
}

// ── Sync filters to URL query ─────────────────────────────────
watch([selectedStatus, currentPage], ([status, page]) => {
  router.replace({
    query: {
      ...(status ? { status } : {}),
      ...(page > 1 ? { page: String(page) } : {}),
      ...(route.query.bookingId ? { bookingId: route.query.bookingId } : {})
    }
  });
  load();
});

// When status filter changes, reset to page 1
watch(selectedStatus, () => {
  currentPage.value = 1;
});

await load();

// ── Scroll-to + highlight from ?bookingId query param ────────────
async function scrollToBooking(id: string) {
  await nextTick();
  await new Promise((r) => setTimeout(r, 80));
  const el = document.getElementById(`booking-${id}`);
  if (!el) return;
  el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  highlightedId.value = id;
  setTimeout(() => {
    highlightedId.value = null;
  }, 5000);
}

async function resolveAndScrollToBooking(id: string) {
  if (bookings.value.some((b) => b.id === id)) {
    scrollToBooking(id);
    return;
  }
  try {
    const page = await findBookingPage(id);
    if (page !== currentPage.value) {
      currentPage.value = page; // triggers load() via watcher
      await new Promise<void>((resolve) => {
        if (!loading.value) return resolve();
        const stop = watch(loading, (v) => {
          if (!v) {
            stop();
            resolve();
          }
        });
      });
    }
    scrollToBooking(id);
  } catch {
    // booking not found or not owned — ignore
  }
}

onMounted(() => {
  const id = route.query.bookingId as string | undefined;
  if (id) resolveAndScrollToBooking(id);
});

watch(
  () => route.query.bookingId,
  (val) => {
    const id = val as string | undefined;
    if (id) resolveAndScrollToBooking(id);
  }
);

// ── Ban state ─────────────────────────────────────────────────
const isBanned = computed(() => {
  const until = authStore.user?.booking_banned_until;
  return !!until && new Date(until).getTime() > Date.now();
});

const banExpiryFormatted = computed(() => {
  const until = authStore.user?.booking_banned_until;
  if (!until) return '';
  return new Date(until).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
});

// ── WebSocket sync ────────────────────────────────────────────
watch(
  () => bookingStore.lastBookingEvent,
  () => {
    load();
  }
);

// ── Date/time helpers ─────────────────────────────────────────
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
}

function formatExpiry(iso: string): string {
  return new Date(iso).toLocaleString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
}

function formatTime(start: string, end: string): string {
  const opts: Intl.DateTimeFormatOptions = {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  };
  const s = new Date(start).toLocaleTimeString('en-PH', opts);
  const e = new Date(end).toLocaleTimeString('en-PH', opts);
  return `${s} – ${e}`;
}

// ── Cancellation ──────────────────────────────────────────────
function openCancel(booking: UserBooking) {
  cancelTarget.value = booking;
  cancelConfirmOpen.value = true;
}

async function submitCancel() {
  if (!cancelTarget.value) return;
  cancelling.value = true;
  try {
    const updated = await cancelMyBooking(cancelTarget.value.id);
    const idx = bookings.value.findIndex((b) => b.id === updated.id);
    if (idx !== -1)
      bookings.value[idx] = { ...bookings.value[idx], ...updated };
    toast.add({ title: 'Booking cancelled', color: 'success' });
    cancelConfirmOpen.value = false;
  } catch (err: unknown) {
    toast.add({
      title: 'Could not cancel',
      description:
        (err as { data?: { message?: string } })?.data?.message ??
        'Please try again.',
      color: 'error'
    });
  } finally {
    cancelling.value = false;
    cancelTarget.value = null;
  }
}

// ── Receipt upload ────────────────────────────────────────────
function openUploadReceipt(booking: UserBooking) {
  receiptTarget.value = booking;
  receiptOpen.value = true;
}

function onReceiptUploaded() {
  receiptOpen.value = false;
  load();
}

// ── Cancellable check ─────────────────────────────────────────
function isCancellable(booking: UserBooking): boolean {
  return (
    booking.status === 'pending_payment' || booking.status === 'payment_sent'
  );
}

// ── Receipt modal adapter ─────────────────────────────────────
const receiptCalendarBooking = computed((): CalendarBooking | null => {
  if (!receiptTarget.value) return null;
  return {
    id: receiptTarget.value.id,
    start_time: receiptTarget.value.start_time,
    end_time: receiptTarget.value.end_time,
    session_type: receiptTarget.value.session_type,
    status: receiptTarget.value.status,
    is_own: true,
    court_id: receiptTarget.value.court?.id,
    expires_at: receiptTarget.value.expires_at ?? undefined
  };
});

function canUploadReceipt(booking: UserBooking): boolean {
  if (booking.status !== 'pending_payment') return false;
  if (!booking.expires_at) return true;
  return new Date(booking.expires_at).getTime() > Date.now();
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="mb-6 text-2xl font-bold text-[var(--aktiv-ink)]">My Bookings</h1>

    <!-- Ban notice -->
    <div
      v-if="isBanned"
      class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      <UIcon name="i-heroicons-no-symbol" class="mt-0.5 h-4 w-4 shrink-0" />
      <span>
        Your account is temporarily restricted from making new bookings until
        <strong>{{ banExpiryFormatted }}</strong
        >. This was triggered by multiple expired bookings.
      </span>
    </div>

    <!-- Receipt reminder -->
    <div
      class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
      <UIcon
        name="i-heroicons-exclamation-triangle"
        class="mt-0.5 h-4 w-4 shrink-0"
      />
      <span>
        Please upload your payment receipt within 1 hour of booking. Repeatedly
        letting bookings expire may result in a temporary booking restriction.
      </span>
    </div>

    <!-- Status filter tabs -->
    <div class="mb-6 flex flex-wrap gap-2">
      <button
        v-for="opt in statusOptions"
        :key="opt.value"
        :class="[
          'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
          selectedStatus === opt.value
            ? 'border-[#004e89] bg-[#004e89] text-white'
            : 'border-[var(--aktiv-border)] bg-white text-[var(--aktiv-ink)] hover:border-[#004e89] hover:text-[#004e89]'
        ]"
        @click="selectedStatus = opt.value"
      >
        {{ opt.label }}
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3">
      <div
        v-for="n in 4"
        :key="n"
        class="h-24 animate-pulse rounded-xl bg-[var(--aktiv-border)]"
      />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="bookings.length === 0"
      class="flex flex-col items-center gap-4 rounded-xl border border-[var(--aktiv-border)] bg-white px-6 py-16 text-center"
    >
      <UIcon
        name="i-heroicons-calendar-days"
        class="h-12 w-12 text-[var(--aktiv-muted)]"
      />
      <template v-if="selectedStatus">
        <p class="text-lg font-semibold text-[var(--aktiv-ink)]">
          No
          {{
            statusConfig[selectedStatus as BookingStatus]?.label ??
            selectedStatus
          }}
          bookings
        </p>
        <p class="text-sm text-[var(--aktiv-muted)]">
          You don't have any bookings with this status.
        </p>
        <UButton variant="outline" color="neutral" @click="selectedStatus = ''">
          View all bookings
        </UButton>
      </template>
      <template v-else>
        <p class="text-lg font-semibold text-[var(--aktiv-ink)]">
          No bookings yet
        </p>
        <p class="text-sm text-[var(--aktiv-muted)]">
          Browse sports hubs and book a court to get started.
        </p>
        <UButton variant="solid" class="bg-[#004e89] hover:bg-[#003d6b]" to="/">
          Explore Hubs
        </UButton>
      </template>
    </div>

    <!-- Booking cards -->
    <div v-else class="space-y-3">
      <div
        v-for="booking in bookings"
        :key="booking.id"
        :id="`booking-${booking.id}`"
        :class="[
          'rounded-xl border bg-white p-4 transition-all duration-500',
          highlightedId === booking.id
            ? 'border-[#004e89] ring-2 ring-[#004e89]/30'
            : 'border-[var(--aktiv-border)]'
        ]"
      >
        <div class="flex items-start justify-between gap-3">
          <!-- Left: hub + court + date -->
          <div class="min-w-0 flex-1">
            <NuxtLink
              v-if="booking.court?.hub?.id"
              :to="`/hubs/${booking.court.hub.id}/about`"
              target="_blank"
              class="truncate font-semibold text-[var(--aktiv-ink)] hover:text-[#004e89] hover:underline transition-colors md:text-lg"
            >
              <div class="flex gap-2 items-center">
                <span>{{ booking.court.hub.name }}</span>

                <UIcon
                  name="i-lucide-square-arrow-out-up-right"
                  class="size-3"
                />
              </div>
            </NuxtLink>
            <p
              v-else
              class="truncate font-semibold text-[var(--aktiv-ink)] md:text-lg"
            >
              —
            </p>
            <p class="mt-0.5 truncate text-sm md:text-base text-[var(--aktiv-muted)]" :title="booking.court?.name">
              {{ booking.court?.name ?? '—' }}
            </p>
            <p class="mt-1 text-sm md:text-base text-[var(--aktiv-ink)]">
              {{ formatDate(booking.start_time) }}
            </p>
            <p class="text-sm md:text-base text-[var(--aktiv-muted)]">
              {{ formatTime(booking.start_time, booking.end_time) }}
            </p>
            <p
              v-if="booking.status === 'pending_payment' && booking.expires_at"
              class="mt-0.5 text-xs md:text-sm text-[var(--aktiv-muted)]"
            >
              Expires at {{ formatExpiry(booking.expires_at) }}
            </p>
          </div>

          <!-- Right: status + price -->
          <div class="flex flex-col items-end gap-1.5 shrink-0">
            <UBadge
              :color="
                statusConfig[effectiveStatus(booking)]?.color ?? 'neutral'
              "
              variant="subtle"
            >
              {{
                statusConfig[effectiveStatus(booking)]?.label ?? booking.status
              }}
            </UBadge>
            <span
              v-if="booking.total_price"
              class="text-3xl font-semibold text-[var(--aktiv-primary)]"
            >
              ₱{{ Number(booking.total_price).toLocaleString('en-PH') }}
            </span>
          </div>
        </div>

        <!-- Payment note (if rejected) -->
        <div
          v-if="booking.payment_note"
          class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700"
        >
          <span class="font-semibold">Note from hub:</span>
          {{ booking.payment_note }}
        </div>

        <!-- Actions row -->
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <UButton
            v-if="canUploadReceipt(booking)"
            variant="outline"
            icon="i-heroicons-arrow-up-tray"
            class="border-[#004e89] text-[#004e89] hover:bg-[#e8f0f8]"
            @click="openUploadReceipt(booking)"
          >
            Upload Receipt
          </UButton>

          <UButton
            v-if="isCancellable(booking)"
            color="error"
            variant="outline"
            icon="i-heroicons-x-mark"
            @click="openCancel(booking)"
          >
            Cancel
          </UButton>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="paginationMeta.last_page > 1" class="mt-6 flex justify-center">
      <UPagination
        v-model:page="currentPage"
        :total="paginationMeta.total"
        :items-per-page="10"
      />
    </div>
  </div>

  <!-- Cancel confirm modal -->
  <AppModal
    v-model:open="cancelConfirmOpen"
    title="Cancel Booking"
    cancel="Keep Booking"
    cancel-variant="outline"
    confirm="Cancel Booking"
    confirm-color="error"
    :confirm-loading="cancelling"
    @confirm="submitCancel"
  >
    <template #body>
      <p class="text-sm text-[var(--aktiv-ink)]">
        Are you sure you want to cancel this booking?
      </p>
      <div
        v-if="cancelTarget"
        class="mt-3 rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] p-3 text-sm"
      >
        <p class="font-semibold">{{ cancelTarget.court?.hub?.name }}</p>
        <p class="text-[var(--aktiv-muted)]">{{ cancelTarget.court?.name }}</p>
        <p class="mt-1">{{ formatDate(cancelTarget.start_time) }}</p>
        <p class="text-[var(--aktiv-muted)]">
          {{ formatTime(cancelTarget.start_time, cancelTarget.end_time) }}
        </p>
      </div>
      <p class="mt-3 text-xs text-[var(--aktiv-muted)]">
        This action cannot be undone.
      </p>
    </template>
  </AppModal>

  <!-- Receipt upload modal (reused scheduler component) -->
  <SchedulerReceiptUploadModal
    v-if="receiptTarget"
    :open="receiptOpen"
    :booking="receiptCalendarBooking"
    :hub-id="receiptTarget.court?.hub?.id ?? ''"
    :court-id="receiptTarget.court?.id ?? ''"
    :court-name="receiptTarget.court?.name"
    @update:open="receiptOpen = $event"
    @receipt-uploaded="onReceiptUploaded"
  />
</template>
