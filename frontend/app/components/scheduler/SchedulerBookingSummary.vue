<script setup lang="ts">
import type { Court, Hub } from '~/types/hub';
import type { Booking, SelectedSlot } from '~/types/booking';
import { useAuthStore } from '~/stores/auth';

const props = defineProps<{
  selectedSlots: SelectedSlot[];
  courts: Court[];
  hubId: string;
  hub: Hub | null | undefined;
}>();

const emit = defineEmits<{
  'booking-created': [];
  'clear': [];
  'remove-slots': [slots: SelectedSlot[]];
}>();

const route = useRoute();
const toast = useToast();
const authStore = useAuthStore();
const { createBooking } = useBooking();

const isLoggedIn = computed(() => authStore.isAuthenticated);
const allowGuests = computed(
  () => props.hub?.require_account_to_book === false
);
const isSubmitting = ref(false);
const submitError = ref<string | null>(null);

const isGuestModalOpen = ref(false);
const isQrModalOpen = ref(false);
const qrBooking = ref<Booking | null>(null);
const qrCourtName = ref<string | undefined>(undefined);

const hubPaymentMethods = computed(
  () => props.hub?.payment_methods ?? ['pay_on_site']
);
const hasAnyPaymentMethod = computed(() => hubPaymentMethods.value.length > 0);
const isDigitalBank = computed(() =>
  hubPaymentMethods.value.includes('digital_bank')
);
const isQrExpanded = ref(false);
const multiplePaymentOptions = computed(
  () => hubPaymentMethods.value.length > 1
); // used for confirm button disable check

// Payment method selection for the confirm modal
const selectedPaymentMethod = ref<'pay_on_site' | 'digital_bank' | null>(null);

watch(
  hubPaymentMethods,
  (methods) => {
    selectedPaymentMethod.value = methods.length === 1 ? (methods[0]! as 'pay_on_site' | 'digital_bank') : null;
  },
  { immediate: true }
);

const isPayOnSite = computed(
  () => selectedPaymentMethod.value === 'pay_on_site'
);

// ── Summary groups ─────────────────────────────────────────────
interface TimeRange {
  start: Date;
  end: Date;
  label: string;
}

interface SummaryGroup {
  key: string; // `${courtId}-${dateKey}`
  court: Court;
  dateKey: string; // YYYY-MM-DD
  dateLabel: string;
  slots: SelectedSlot[];
  totalHours: number;
  pricePerHour: number | null;
  subtotal: number | null;
  ranges: TimeRange[];
}

function formatTime12(date: Date): string {
  const h = date.getHours();
  const m = date.getMinutes();
  const ampm = h < 12 ? 'AM' : 'PM';
  const h12 = h % 12 || 12;
  return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}

function mergeContiguousSlots(sortedSlots: SelectedSlot[]): TimeRange[] {
  if (!sortedSlots.length) return [];
  const ranges: TimeRange[] = [];
  let start = new Date(sortedSlots[0]!.slotStart);
  let end = new Date(start.getTime() + 3_600_000);

  for (let i = 1; i < sortedSlots.length; i++) {
    const slotTime = sortedSlots[i]!.slotStart.getTime();
    if (slotTime === end.getTime()) {
      end = new Date(slotTime + 3_600_000);
    } else {
      ranges.push({
        start,
        end,
        label: `${formatTime12(start)} – ${formatTime12(end)}`
      });
      start = new Date(sortedSlots[i]!.slotStart);
      end = new Date(start.getTime() + 3_600_000);
    }
  }
  ranges.push({
    start,
    end,
    label: `${formatTime12(start)} – ${formatTime12(end)}`
  });
  return ranges;
}

const summaryGroups = computed<SummaryGroup[]>(() => {
  const groups: Record<string, SummaryGroup> = {};

  for (const slot of props.selectedSlots) {
    const court = props.courts.find((c) => c.id === slot.courtId);
    if (!court) continue;

    const d = slot.slotStart;
    const dateKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const key = `${slot.courtId}-${dateKey}`;

    if (!groups[key]) {
      groups[key] = {
        key,
        court,
        dateKey,
        dateLabel: slot.slotStart.toLocaleDateString('en-PH', {
          weekday: 'short',
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        }),
        slots: [],
        totalHours: 0,
        pricePerHour: null,
        subtotal: null,
        ranges: []
      };
    }
    groups[key]!.slots.push(slot);
  }

  return Object.values(groups)
    .map((group) => {
      group.slots.sort((a, b) => a.slotStart.getTime() - b.slotStart.getTime());
      group.totalHours = group.slots.length;
      const priceNum = parseFloat(group.court.price_per_hour);
      group.pricePerHour = isNaN(priceNum) ? null : priceNum;
      group.subtotal =
        group.pricePerHour !== null
          ? group.pricePerHour * group.totalHours
          : null;
      group.ranges = mergeContiguousSlots(group.slots);
      return group;
    })
    .sort((a, b) =>
      a.dateKey !== b.dateKey
        ? a.dateKey.localeCompare(b.dateKey)
        : a.court.name.localeCompare(b.court.name)
    );
});

const totalSlots = computed(() => props.selectedSlots.length);

const grandTotal = computed(() =>
  summaryGroups.value.reduce((sum, g) => sum + (g.subtotal ?? 0), 0)
);

const totalBookingsToCreate = computed(() =>
  summaryGroups.value.reduce((sum, g) => sum + g.ranges.length, 0)
);

function formatPrice(n: number): string {
  return n.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatPriceInt(n: number): string {
  return n.toLocaleString('en-PH', { maximumFractionDigits: 0 });
}

// ── Actions ────────────────────────────────────────────────────
function removeSlotsForGroup(group: SummaryGroup) {
  emit('remove-slots', group.slots);
}

function goToLogin() {
  navigateTo(`/auth/login?redirect=${encodeURIComponent(route.fullPath)}`);
}

const isConfirmOpen = ref(false);

function handleBookNow() {
  if (!isLoggedIn.value) {
    goToLogin();
    return;
  }
  if (!authStore.user?.email_verified_at) {
    navigateTo('/auth/verify-email');
    return;
  }
  // Reset payment selection when opening
  selectedPaymentMethod.value =
    hubPaymentMethods.value.length === 1
      ? (hubPaymentMethods.value[0]! as 'pay_on_site' | 'digital_bank')
      : null;
  isConfirmOpen.value = true;
}

async function submitBooking() {
  isSubmitting.value = true;
  submitError.value = null;

  const tasks: (() => Promise<unknown>)[] = [];

  for (const group of summaryGroups.value) {
    for (const range of group.ranges) {
      tasks.push(() =>
        createBooking(props.hubId, group.court.id, {
          start_time: range.start.toISOString(),
          end_time: range.end.toISOString(),
          session_type: 'private',
          payment_method: selectedPaymentMethod.value!
        })
      );
    }
  }

  try {
    const results = (await Promise.all(tasks.map((t) => t()))) as Booking[];
    isConfirmOpen.value = false;
    const n = tasks.length;

    if (isPayOnSite.value && results[0]) {
      // Show QR code for first booking; customer can check other booking codes in history
      qrBooking.value = results[0];
      const firstGroup = summaryGroups.value[0];
      qrCourtName.value = firstGroup
        ? `${firstGroup.court.name} · ${firstGroup.dateLabel}`
        : undefined;
      isQrModalOpen.value = true;
    } else {
      toast.add({
        title: n === 1 ? 'Booking created!' : `${n} bookings created!`,
        description:
          'Your slot(s) are held for 1 hour. Upload your payment receipt to confirm.',
        color: 'success'
      });
    }
    emit('booking-created');
    emit('clear');
  } catch (e: unknown) {
    const err = e as { data?: { message?: string }; status?: number };
    submitError.value =
      err?.status === 409
        ? 'One or more time slots were just taken. Please update your selection and try again.'
        : (err?.data?.message ?? 'Booking failed. Please try again.');
    // Refresh grid so user sees updated availability
    emit('booking-created');
  } finally {
    isSubmitting.value = false;
  }
}

function scrollToSchedule() {
  const el = document.getElementById('schedule');
  if (!el) return;
  const top = el.getBoundingClientRect().top + window.scrollY - 140;
  window.scrollTo({ top, behavior: 'smooth' });
}
</script>

<template>
  <div
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)]"
  >
    <!-- Header -->
    <div
      class="flex items-center justify-between border-b border-[var(--aktiv-border)] px-5 py-4"
    >
      <div class="flex items-center gap-2.5">
        <h2 class="text-base font-bold text-[var(--aktiv-ink)]">
          Booking Summary
        </h2>
        <UBadge v-if="totalSlots > 0" color="primary" variant="solid">
          {{ totalSlots }} slot{{ totalSlots !== 1 ? 's' : '' }}
        </UBadge>
      </div>
      <button
        v-if="totalSlots > 0"
        type="button"
        class="text-sm text-[var(--aktiv-muted)] underline underline-offset-2 transition-colors hover:text-[var(--aktiv-ink)]"
        @click="emit('clear')"
      >
        Clear all
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="totalSlots === 0" class="px-5 py-10 text-center">
      <UIcon
        name="i-heroicons-calendar-days"
        class="mx-auto mb-3 h-9 w-9 text-[var(--aktiv-border)]"
      />
      <p class="text-sm font-medium text-[var(--aktiv-ink)]">
        No slots selected yet
      </p>
      <p class="mt-1 text-sm text-[var(--aktiv-muted)]">
        Click any available time slot in the grid above to add it to your
        booking.
      </p>
      <UButton
        label="Book a Court"
        icon="i-heroicons-calendar-days"
        color="primary"
        size="xl"
        block
        class="mt-4"
        @click="scrollToSchedule"
      />
    </div>

    <template v-else>
      <!-- ── Per-group breakdown ───────────────────────────── -->
      <div class="divide-y divide-[var(--aktiv-border)]">
        <div v-for="group in summaryGroups" :key="group.key" class="px-5 py-4">
          <!-- Court + date row -->
          <div class="mb-3 flex items-start justify-between gap-2">
            <div>
              <span class="font-semibold text-[var(--aktiv-ink)]">
                {{ group.court.name }}
              </span>
              <span class="ml-2 text-sm text-[var(--aktiv-muted)]">
                · {{ group.dateLabel }}
              </span>
            </div>
            <button
              type="button"
              class="rounded p-0.5 text-[var(--aktiv-muted)] transition-colors hover:bg-[var(--aktiv-border)] hover:text-[var(--aktiv-ink)]"
              @click="removeSlotsForGroup(group)"
            >
              <UIcon name="i-heroicons-x-mark" class="h-4 w-4" />
            </button>
          </div>

          <!-- Time range chips -->
          <div class="mb-3 flex flex-wrap gap-1.5">
            <span
              v-for="(range, i) in group.ranges"
              :key="i"
              class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-2.5 py-1 text-sm text-[var(--aktiv-ink)]"
            >
              <UIcon
                name="i-heroicons-clock"
                class="h-3.5 w-3.5 text-[var(--aktiv-muted)]"
              />
              {{ range.label }}
            </span>
          </div>

          <!-- Price breakdown row -->
          <div class="flex items-center justify-between">
            <span class="text-sm text-[var(--aktiv-muted)]">
              {{ group.totalHours }} hr{{ group.totalHours !== 1 ? 's' : '' }}
              <template v-if="group.pricePerHour !== null">
                × ₱{{ formatPriceInt(group.pricePerHour) }}/hr
              </template>
            </span>
            <span class="font-semibold text-[var(--aktiv-ink)]">
              <template v-if="group.subtotal !== null">
                ₱{{ formatPrice(group.subtotal) }}
              </template>
              <span v-else class="text-sm text-[var(--aktiv-muted)]">—</span>
            </span>
          </div>
        </div>
      </div>

      <!-- ── Total ─────────────────────────────────────────── -->
      <div class="border-t border-[var(--aktiv-border)] px-5 py-4">
        <div class="flex items-center justify-between">
          <span class="font-semibold text-[var(--aktiv-ink)]">Total</span>
          <span class="text-2xl font-black text-[var(--aktiv-ink)]">
            ₱{{ formatPrice(grandTotal) }}
          </span>
        </div>
        <p
          v-if="totalBookingsToCreate > 1"
          class="mt-0.5 text-right text-sm text-[var(--aktiv-muted)]"
        >
          {{ totalBookingsToCreate }} separate booking{{
            totalBookingsToCreate !== 1 ? 's' : ''
          }}
          will be created
        </p>
      </div>

      <!-- ── Payment note ───────────────────────────────────── -->
      <div class="border-t border-[var(--aktiv-border)] px-5 py-3">
        <div class="flex items-start gap-2 text-sm text-[var(--aktiv-muted)]">
          <UIcon
            name="i-heroicons-information-circle"
            class="mt-0.5 h-4 w-4 shrink-0"
          />
          <span
            v-if="hubPaymentMethods.includes('pay_on_site') && !isDigitalBank"
          >
            After booking you'll receive a
            <strong class="font-semibold text-[var(--aktiv-ink)]"
              >booking code & QR</strong
            >. Show it at the venue — the hub owner will scan it to confirm your
            payment.
          </span>
          <span
            v-else-if="
              isDigitalBank && !hubPaymentMethods.includes('pay_on_site')
            "
          >
            Slots are held for
            <strong class="font-semibold text-[var(--aktiv-ink)]"
              >1 hour</strong
            >
            after booking. Upload your GCash or bank transfer receipt within
            that window to confirm.
          </span>
          <span v-else>
            Choose your preferred payment method when booking.
          </span>
        </div>
      </div>

      <!-- ── Error ──────────────────────────────────────────── -->
      <div v-if="submitError" class="px-5 pb-4">
        <UAlert color="error" variant="soft" :title="submitError" />
      </div>

      <!-- ── Book Now / Login / Guest ─────────────────────── -->
      <div class="border-t border-[var(--aktiv-border)] px-5 py-4">
        <UButton
          v-if="isLoggedIn"
          block
          size="lg"
          color="primary"
          :disabled="totalSlots === 0"
          @click="handleBookNow"
        >
          Book Now · {{ totalSlots }} slot{{ totalSlots !== 1 ? 's' : '' }}
        </UButton>
        <template v-else-if="allowGuests">
          <UButton
            block
            size="lg"
            color="primary"
            :disabled="totalSlots === 0"
            @click="isGuestModalOpen = true"
          >
            Book as Guest
          </UButton>
          <p class="mt-2 text-center text-xs text-[var(--aktiv-muted)]">
            or
            <button
              type="button"
              class="font-medium text-[#004e89] underline underline-offset-2"
              @click="goToLogin"
            >
              log in
            </button>
            for full booking history
          </p>
        </template>
        <UButton
          v-else
          block
          size="lg"
          color="primary"
          variant="outline"
          @click="goToLogin"
        >
          Log in to Book
        </UButton>
      </div>
    </template>
  </div>

  <!-- ── QR code modal (pay on site) ─────────────────────────── -->
  <BookingQrCodeModal
    v-model:open="isQrModalOpen"
    :booking="qrBooking"
    :court-name="qrCourtName"
  />

  <!-- ── Guest booking modal ──────────────────────────────────── -->
  <SchedulerGuestBookingModal
    v-model:open="isGuestModalOpen"
    :selected-slots="selectedSlots"
    :courts="courts"
    :hub-id="hubId"
    :hub="hub"
    @booking-created="
      () => {
        emit('booking-created');
        emit('clear');
      }
    "
  />

  <!-- ── Confirmation dialog ──────────────────────────────────── -->
  <AppModal
    v-model:open="isConfirmOpen"
    title="Confirm Booking"
    :ui="{ content: 'max-w-lg' }"
    confirm="Confirm Booking"
    :confirm-loading="isSubmitting"
    :confirm-disabled="multiplePaymentOptions && !selectedPaymentMethod"
    @confirm="submitBooking"
  >
    <template #body>
      <!-- Per-group summary -->
      <div class="divide-y divide-[var(--aktiv-border)]">
        <div
          v-for="group in summaryGroups"
          :key="group.key"
          class="py-3 first:pt-0 last:pb-0"
        >
          <div class="mb-1.5 flex items-center justify-between">
            <span class="font-semibold text-[var(--aktiv-ink)]">
              {{ group.court.name }}
            </span>
            <span class="text-sm text-[var(--aktiv-muted)]">
              {{ group.dateLabel }}
            </span>
          </div>
          <!-- Time range chips -->
          <div class="mb-1.5 flex flex-wrap gap-1.5">
            <span
              v-for="(range, i) in group.ranges"
              :key="i"
              class="inline-flex items-center gap-1 rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-2 py-0.5 text-sm text-[var(--aktiv-ink)]"
            >
              <UIcon
                name="i-heroicons-clock"
                class="h-3.5 w-3.5 text-[var(--aktiv-muted)]"
              />
              {{ range.label }}
            </span>
          </div>
          <!-- Price row -->
          <div class="flex items-center justify-end text-sm">
            <span class="text-[var(--aktiv-muted)]">
              {{ group.totalHours }} hr{{ group.totalHours !== 1 ? 's' : '' }}
              <template v-if="group.pricePerHour !== null">
                × ₱{{ formatPriceInt(group.pricePerHour) }}/hr
              </template>
              <strong
                v-if="group.subtotal !== null"
                class="ml-2 text-[var(--aktiv-ink)]"
              >
                ₱{{ formatPrice(group.subtotal) }}
              </strong>
            </span>
          </div>
        </div>
      </div>

      <!-- Grand total -->
      <div
        class="mt-4 flex items-center justify-between border-t border-[var(--aktiv-border)] pt-4"
      >
        <span class="font-semibold text-[var(--aktiv-ink)]">Total</span>
        <span class="text-xl font-black text-[var(--aktiv-ink)]">
          ₱{{ formatPrice(grandTotal) }}
        </span>
      </div>

      <!-- Payment method selector -->
      <BookingPaymentMethodSelector
        v-model="selectedPaymentMethod"
        :hub="hub"
        class="mt-4"
      />

      <!-- Payment note -->
      <p class="mt-3 text-sm text-[var(--aktiv-muted)]">
        <template v-if="isPayOnSite">
          After booking you'll receive a
          <strong class="font-semibold text-[var(--aktiv-ink)]"
            >booking code & QR</strong
          >. Show it at the venue — the hub owner will scan it to confirm your
          payment.
        </template>
        <template v-else-if="selectedPaymentMethod === 'digital_bank'">
          Slots are held for
          <strong class="font-semibold text-[var(--aktiv-ink)]">1 hour</strong>
          after booking. Upload your receipt within that window to confirm.
        </template>
        <template v-else-if="!multiplePaymentOptions">
          Slots are held for
          <strong class="font-semibold text-[var(--aktiv-ink)]">1 hour</strong>
          after booking. Upload your receipt within that window to confirm.
        </template>
      </p>

      <!-- Error -->
      <UAlert
        v-if="submitError"
        color="error"
        variant="soft"
        :title="submitError"
        class="mt-3"
      />
    </template>
  </AppModal>
</template>
