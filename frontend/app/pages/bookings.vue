<script setup lang="ts">
import type {
  BookingStatus,
  CalendarBooking,
  MyBookingItem
} from '~/types/booking';
import type { OpenPlayParticipant, OpenPlaySession } from '~/types/openPlay';
import { getOpenPlayBookingPresentation } from '~/utils/openPlayPresentation';

definePageMeta({ middleware: ['auth'], layout: 'page' });

useHead({ title: 'My Bookings · Aktiv' });

const { fetchMyBookings, cancelMyBooking, findBookingPage } = useBooking();
const bookingStore = useUserBookingStore();
const authStore = useAuthStore();
const toast = useToast();
const route = useRoute();
const router = useRouter();

// ── State ─────────────────────────────────────────────────────
const bookings = ref<MyBookingItem[]>([]);
const paginationMeta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);

const selectedStatus = ref<string>((route.query.status as string) ?? '');
const currentPage = ref(Number(route.query.page) || 1);

const highlightedId = ref<string | null>(null);

const cancelTarget = ref<MyBookingItem | null>(null);
const cancelConfirmOpen = ref(false);
const cancelling = ref(false);

const receiptTarget = ref<MyBookingItem | null>(null);
const bookingReceiptOpen = ref(false);
const openPlayReceiptOpen = ref(false);

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

function effectiveStatus(booking: MyBookingItem): DisplayStatus {
  if (booking.status === 'cancelled' && booking.cancelled_by === 'system')
    return 'expired';
  return booking.status;
}

function bookingBadge(booking: MyBookingItem): {
  label: string;
  color: 'warning' | 'info' | 'success' | 'error' | 'neutral';
} {
  if (booking.entry_type === 'open_play_participant') {
    const presentation = getOpenPlayBookingPresentation(booking);

    return {
      label: presentation.label,
      color: presentation.color === 'primary' ? 'neutral' : presentation.color
    };
  }

  return statusConfig[effectiveStatus(booking)] ?? statusConfig.completed;
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
      ...(route.query.itemId ? { itemId: route.query.itemId } : {}),
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

// ── Scroll-to + highlight from ?itemId / ?bookingId query params ────────────
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
  const id =
    (route.query.itemId as string | undefined) ??
    (route.query.bookingId as string | undefined);
  if (id) resolveAndScrollToBooking(id);
});

watch(
  () => [route.query.itemId, route.query.bookingId],
  ([itemId, bookingId]) => {
    const id =
      (itemId as string | undefined) ?? (bookingId as string | undefined);
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
function openCancel(booking: MyBookingItem) {
  cancelTarget.value = booking;
  cancelConfirmOpen.value = true;
}

async function submitCancel() {
  if (!cancelTarget.value) return;
  cancelling.value = true;
  try {
    const updated = await cancelMyBooking(
      cancelTarget.value.entry_type,
      cancelTarget.value.id
    );
    const idx = bookings.value.findIndex((b) => b.id === updated.id);
    if (idx !== -1)
      bookings.value[idx] = { ...bookings.value[idx], ...updated };
    toast.add({
      title:
        cancelTarget.value.entry_type === 'open_play_participant'
          ? 'Open play join cancelled'
          : 'Booking cancelled',
      color: 'success'
    });
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
function openUploadReceipt(booking: MyBookingItem) {
  receiptTarget.value = booking;
  if (booking.entry_type === 'open_play_participant') {
    openPlayReceiptOpen.value = true;
    return;
  }

  bookingReceiptOpen.value = true;
}

function onReceiptUploaded() {
  bookingReceiptOpen.value = false;
  openPlayReceiptOpen.value = false;
  load();
}

// ── Cancellable check ─────────────────────────────────────────
function isCancellable(booking: MyBookingItem): boolean {
  return (
    booking.status === 'pending_payment' || booking.status === 'payment_sent'
  );
}

// ── Receipt modal adapter ─────────────────────────────────────
const receiptCalendarBooking = computed((): CalendarBooking | null => {
  if (!receiptTarget.value || receiptTarget.value.entry_type !== 'booking')
    return null;
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

const receiptOpenPlaySession = computed((): OpenPlaySession | null => {
  if (!receiptTarget.value || receiptTarget.value.entry_type !== 'open_play_participant')
    return null;

  return {
    id: receiptTarget.value.session_id ?? '',
    booking_id: receiptTarget.value.booking_id ?? '',
    max_players: receiptTarget.value.max_players ?? 0,
    price_per_player: receiptTarget.value.price_per_player ?? '0',
    notes: null,
    guests_can_join: false,
    status: 'open',
    booking: {
      id: receiptTarget.value.booking_id ?? '',
      court_id: receiptTarget.value.court?.id ?? '',
      court: receiptTarget.value.court
        ? {
            id: receiptTarget.value.court.id,
            name: receiptTarget.value.court.name
          }
        : null,
      start_time: receiptTarget.value.start_time,
      end_time: receiptTarget.value.end_time,
      status: 'confirmed'
    },
    participants_count: receiptTarget.value.participants_count ?? 0,
    confirmed_participants_count: receiptTarget.value.participants_count ?? 0,
    viewer_participant: null,
    created_at: receiptTarget.value.created_at
  };
});

const receiptOpenPlayParticipant = computed((): OpenPlayParticipant | null => {
  if (!receiptTarget.value || receiptTarget.value.entry_type !== 'open_play_participant')
    return null;

  return {
    id: receiptTarget.value.participant_id ?? receiptTarget.value.id,
    open_play_session_id: receiptTarget.value.session_id ?? '',
    user_id: authStore.user?.id ?? null,
    user: authStore.user
      ? {
          id: authStore.user.id,
          first_name: authStore.user.first_name,
          last_name: authStore.user.last_name,
          email: authStore.user.email,
          contact_number: authStore.user.contact_number,
          avatar_url: authStore.user.avatar_url
        }
      : null,
    guest_name: null,
    guest_phone: null,
    guest_email: null,
    guest_tracking_token: null,
    payment_method:
      receiptTarget.value.payment_method === 'pay_on_site'
        ? 'pay_on_site'
        : 'digital_bank',
    payment_status: receiptTarget.value.status,
    receipt_image_url: receiptTarget.value.receipt_image_url,
    receipt_uploaded_at: receiptTarget.value.receipt_uploaded_at,
    payment_note: receiptTarget.value.payment_note,
    payment_confirmed_by: null,
    payment_confirmed_at: null,
    expires_at: receiptTarget.value.expires_at,
    cancelled_by: receiptTarget.value.cancelled_by,
    joined_at: receiptTarget.value.created_at,
    created_at: receiptTarget.value.created_at
  };
});

function canUploadReceipt(booking: MyBookingItem): boolean {
  if (booking.status !== 'pending_payment') return false;
  if (booking.payment_method === 'pay_on_site') return false;
  if (!booking.expires_at) return true;
  return new Date(booking.expires_at).getTime() > Date.now();
}

function isOpenPlayJoin(booking: MyBookingItem): boolean {
  return booking.entry_type === 'open_play_participant';
}

function displayTitle(booking: MyBookingItem): string {
  return booking.court?.hub?.name ?? '—';
}

function displaySubtitle(booking: MyBookingItem): string {
  if (booking.entry_type === 'open_play_participant') {
    return `Open Play${booking.court?.name ? ` · ${booking.court.name}` : ''}`;
  }

  return booking.court?.name ?? '—';
}

function displayActionLabel(booking: MyBookingItem): string {
  return booking.entry_type === 'open_play_participant' ? 'Leave' : 'Cancel';
}
</script>

<template>
  <div class="mx-auto">
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
        Please upload your payment receipt before your scheduled booking time.
        Repeatedly letting bookings expire may result in a temporary booking
        restriction.
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
                <span>{{ displayTitle(booking) }}</span>

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
            <p
              class="mt-0.5 truncate text-sm md:text-base text-[var(--aktiv-muted)]"
              :title="booking.court?.name"
            >
              {{ displaySubtitle(booking) }}
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
              :color="bookingBadge(booking).color"
              variant="subtle"
            >
              {{ bookingBadge(booking).label }}
            </UBadge>
            <template v-if="booking.entry_type === 'open_play_participant'">
              <span class="text-3xl font-semibold text-[var(--aktiv-primary)]">
                {{
                  booking.price_per_player
                    ? `₱${Number(booking.price_per_player).toLocaleString('en-PH')}`
                    : 'Free'
                }}
              </span>
              <span
                class="inline-flex rounded-full bg-teal-100 px-3 py-1 text-sm font-semibold uppercase tracking-wide text-teal-800"
              >
                Open Play
              </span>
            </template>
            <template v-else-if="booking.total_price">
              <!-- Promo label -->
              <span
                v-if="booking.applied_promo_title"
                class="text-xs font-medium text-[#92400e]"
              >
                {{ booking.applied_promo_title }}
              </span>
              <!-- Original price (strikethrough) -->
              <span
                v-if="booking.original_price"
                class="text-sm line-through text-[var(--aktiv-muted)]"
              >
                ₱{{ Number(booking.original_price).toLocaleString('en-PH') }}
              </span>
              <!-- Discounted / final price -->
              <span class="text-3xl font-semibold text-[var(--aktiv-primary)]">
                ₱{{ Number(booking.total_price).toLocaleString('en-PH') }}
              </span>
              <!-- Saved badge -->
              <span
                v-if="booking.discount_amount"
                class="rounded-full bg-[#fde68a] px-2.5 py-0.5 text-xs font-semibold text-[#854d0e]"
              >
                Saved ₱{{
                  Number(booking.discount_amount).toLocaleString('en-PH')
                }}
              </span>
            </template>
          </div>
        </div>

        <!-- Booking code + QR -->
        <div
          v-if="booking.entry_type === 'booking' && booking.booking_code"
          class="mt-3 flex items-center gap-3 border-t border-[var(--aktiv-border)] pt-3"
        >
          <AppImageViewer
            :src="`/api/bookings/${booking.booking_code}/qr`"
            alt="QR code"
            wrapper-class="shrink-0 rounded-lg overflow-hidden border border-[var(--aktiv-border)] bg-white p-1"
            image-class="block h-12 w-12 object-contain"
          />
          <div>
            <p class="text-xs text-[var(--aktiv-muted)]">Booking Code</p>
            <p
              class="font-mono font-bold tracking-widest text-[var(--aktiv-ink)]"
            >
              {{ booking.booking_code }}
            </p>
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
            {{ displayActionLabel(booking) }}
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
    :title="
      cancelTarget?.entry_type === 'open_play_participant'
        ? 'Leave Open Play'
        : 'Cancel Booking'
    "
    cancel="Keep Booking"
    cancel-variant="outline"
    :confirm="
      cancelTarget?.entry_type === 'open_play_participant'
        ? 'Leave Session'
        : 'Cancel Booking'
    "
    confirm-color="error"
    :confirm-loading="cancelling"
    @confirm="submitCancel"
  >
    <template #body>
      <p class="text-sm text-[var(--aktiv-ink)]">
        {{
          cancelTarget?.entry_type === 'open_play_participant'
            ? 'Are you sure you want to leave this open play session?'
            : 'Are you sure you want to cancel this booking?'
        }}
      </p>
      <div
        v-if="cancelTarget"
        class="mt-3 rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] p-3 text-sm"
      >
        <p class="font-semibold">{{ cancelTarget.court?.hub?.name }}</p>
        <p class="text-[var(--aktiv-muted)]">{{ displaySubtitle(cancelTarget) }}</p>
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
    v-if="receiptTarget?.entry_type === 'booking'"
    :open="bookingReceiptOpen"
    :booking="receiptCalendarBooking"
    :hub-id="receiptTarget.court?.hub?.id ?? ''"
    :court-id="receiptTarget.court?.id ?? ''"
    :court-name="receiptTarget.court?.name"
    @update:open="bookingReceiptOpen = $event"
    @receipt-uploaded="onReceiptUploaded"
  />

  <OpenPlayReceiptUploadModal
    v-if="receiptTarget?.entry_type === 'open_play_participant'"
    :open="openPlayReceiptOpen"
    :hub-id="receiptTarget.court?.hub?.id ?? ''"
    :session="receiptOpenPlaySession"
    :participant="receiptOpenPlayParticipant"
    @update:open="openPlayReceiptOpen = $event"
    @uploaded="onReceiptUploaded"
  />
</template>
