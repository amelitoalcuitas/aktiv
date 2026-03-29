<script setup lang="ts">
import type { Court, Hub } from '~/types/hub';
import type { AppliedDiscount, Booking, SelectedSlot, SessionType, VoucherPreview } from '~/types/booking';

const props = defineProps<{
  open: boolean;
  selectedSlots: SelectedSlot[];
  courts: Court[];
  hubId: string;
  hub: Hub | null | undefined;
  voucherCode?: string;
  voucherPreview?: VoucherPreview | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'booking-created': [];
}>();

const toast = useToast();
const { sendGuestVerificationCode, createGuestBooking, previewVoucher } = useBooking();

const isQrModalOpen = ref(false);
const qrBooking = ref<Booking | null>(null);
const qrCourtName = ref<string | undefined>(undefined);

const hubPaymentMethods = computed(() => props.hub?.payment_methods ?? ['pay_on_site']);

// ── Step state ────────────────────────────────────────────────
const step = ref<'details' | 'verify'>('details');

// ── Form fields ───────────────────────────────────────────────
const guestName = ref('');
const email = ref('');
const guestPhone = ref('');
// Auto-select if only one method; null forces user to choose when multiple are available
const selectedPaymentMethod = ref<'pay_on_site' | 'digital_bank' | null>(null);

watch(hubPaymentMethods, (methods) => {
  if (methods.length === 1) {
    selectedPaymentMethod.value = methods[0]! as 'pay_on_site' | 'digital_bank';
  } else {
    selectedPaymentMethod.value = null;
  }
}, { immediate: true });

const isPayOnSite = computed(() => selectedPaymentMethod.value === 'pay_on_site');
const otp = ref('');
const appliedVoucherPreview = computed(() => props.voucherPreview ?? null);

// ── Derived booking info ──────────────────────────────────────

interface TimeRange {
  start: Date;
  end: Date;
  label: string;
}

interface GuestGroup {
  court: Court;
  dateLabel: string;
  dateKey: string;
  ranges: TimeRange[];
  totalHours: number;
  pricePerHour: number | null;
  subtotal: number | null;
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
    const t = sortedSlots[i]!.slotStart.getTime();
    if (t === end.getTime()) {
      end = new Date(t + 3_600_000);
    } else {
      ranges.push({ start, end, label: `${formatTime12(start)} – ${formatTime12(end)}` });
      start = new Date(sortedSlots[i]!.slotStart);
      end = new Date(start.getTime() + 3_600_000);
    }
  }
  ranges.push({ start, end, label: `${formatTime12(start)} – ${formatTime12(end)}` });
  return ranges;
}

const groups = computed<GuestGroup[]>(() => {
  const map: Record<string, { slots: SelectedSlot[]; court: Court; dateKey: string; dateLabel: string }> = {};

  for (const slot of props.selectedSlots) {
    const court = props.courts.find((c) => c.id === slot.courtId);
    if (!court) continue;
    const d = slot.slotStart;
    const dateKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const key = `${slot.courtId}-${dateKey}`;
    if (!map[key]) {
      map[key] = {
        court,
        dateKey,
        dateLabel: d.toLocaleDateString('en-PH', {
          weekday: 'short', month: 'long', day: 'numeric', year: 'numeric'
        }),
        slots: []
      };
    }
    map[key]!.slots.push(slot);
  }

  return Object.entries(map).map(([, { court, dateKey, dateLabel, slots }]) => {
    const sorted = [...slots].sort((a, b) => a.slotStart.getTime() - b.slotStart.getTime());
    const ranges = mergeContiguousSlots(sorted);
    const priceNum = parseFloat(court.price_per_hour);
    const pricePerHour = isNaN(priceNum) ? null : priceNum;
    const totalHours = sorted.length;
    const subtotal = pricePerHour !== null ? pricePerHour * totalHours : null;

    return {
      court,
      dateKey,
      dateLabel,
      ranges,
      totalHours,
      pricePerHour,
      subtotal
    };
  });
});

const guestMaxHours = computed(() => props.hub?.guest_max_hours ?? 2);

// Check if any contiguous range exceeds the hub's guest max hours
const hasOversizedRange = computed(() =>
  groups.value.some((g) =>
    g.ranges.some((r) => (r.end.getTime() - r.start.getTime()) > guestMaxHours.value * 3_600_000)
  )
);

const grandTotal = computed(() =>
  groups.value.reduce((sum, g) => sum + (g.subtotal ?? 0), 0)
);

function normalizeIsoKeyPart(value: Date | string): string {
  if (value instanceof Date) return value.toISOString();

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? value : parsed.toISOString();
}

function rangeKey(courtId: string, start: Date | string, end: Date | string): string {
  const startIso = normalizeIsoKeyPart(start);
  const endIso = normalizeIsoKeyPart(end);
  return `${courtId}-${startIso}-${endIso}`;
}

const voucherItemMap = computed(() => new Map(
  (appliedVoucherPreview.value?.items ?? []).map((item) => [
    rangeKey(item.court_id, item.start_time, item.end_time),
    item
  ])
));

function groupVoucherSubtotal(group: GuestGroup): number | null {
  if (!appliedVoucherPreview.value) return group.subtotal;

  return group.ranges.reduce((sum, range) => {
    const item = voucherItemMap.value.get(
      rangeKey(group.court.id, range.start, range.end)
    );
    return sum + (item?.discounted_price ?? 0);
  }, 0);
}

function groupVoucherOriginalSubtotal(group: GuestGroup): number | null {
  if (!appliedVoucherPreview.value) return group.subtotal;

  return group.ranges.reduce((sum, range) => {
    const item = voucherItemMap.value.get(
      rangeKey(group.court.id, range.start, range.end)
    );
    return sum + (item?.original_price ?? 0);
  }, 0);
}

const displayedGrandTotal = computed(() =>
  appliedVoucherPreview.value?.summary.discounted_total ?? grandTotal.value
);

const displayedOriginalTotal = computed(() =>
  appliedVoucherPreview.value?.summary.original_total ?? null
);

const appliedDiscountMeta = computed<AppliedDiscount | null>(
  () => appliedVoucherPreview.value?.applied_discount ?? null
);

function formatPrice(n: number) {
  return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── Step 1: Send OTP ──────────────────────────────────────────
const isSendingCode = ref(false);
const sendError = ref<string | null>(null);

async function handleSendCode() {
  if (!email.value || !guestName.value || !guestPhone.value) return;
  if (hasOversizedRange.value) return;

  const firstGroup = groups.value[0];
  if (!firstGroup) return;

  isSendingCode.value = true;
  sendError.value = null;
  try {
    if (props.voucherCode) {
      await previewVoucher(props.hubId, {
        voucher_code: props.voucherCode,
        guest_email: email.value,
        items: groups.value.flatMap((group) =>
          group.ranges.map((range) => ({
            court_id: group.court.id,
            start_time: range.start.toISOString(),
            end_time: range.end.toISOString()
          }))
        )
      });
    }

    await sendGuestVerificationCode(props.hubId, firstGroup.court.id, email.value);
    step.value = 'verify';
  } catch (e: unknown) {
    const err = e as { data?: { message?: string; errors?: Record<string, string[]> } };
    sendError.value =
      err?.data?.errors?.voucher_code?.[0] ??
      err?.data?.message ??
      'Failed to send code. Please try again.';
  } finally {
    isSendingCode.value = false;
  }
}

// ── Step 2: Verify & Book ─────────────────────────────────────
const isBooking = ref(false);
const bookingError = ref<string | null>(null);

async function handleVerifyAndBook() {
  if (!otp.value || otp.value.length !== 6) return;

  isBooking.value = true;
  bookingError.value = null;

  const tasks: (() => Promise<unknown>)[] = [];

  for (const group of groups.value) {
    for (const range of group.ranges) {
      tasks.push(() =>
        createGuestBooking(props.hubId, group.court.id, {
          email: email.value,
          otp: otp.value,
          guest_name: guestName.value,
          guest_phone: guestPhone.value,
          start_time: range.start.toISOString(),
          end_time: range.end.toISOString(),
          session_type: 'private' as SessionType,
          payment_method: selectedPaymentMethod.value!,
          voucher_code: props.voucherCode ?? null
        })
      );
    }
  }

  try {
    const results = await Promise.all(tasks.map((t) => t())) as Booking[];
    const n = tasks.length;

    emit('booking-created');
    emit('update:open', false);
    resetForm();

    if (isPayOnSite.value && results[0]) {
      qrBooking.value = results[0];
      const firstGroup = groups.value[0];
      qrCourtName.value = firstGroup ? `${firstGroup.court.name} · ${firstGroup.dateLabel}` : undefined;
      isQrModalOpen.value = true;
    } else {
      toast.add({
        title: n === 1 ? 'Booking created!' : `${n} bookings created!`,
        description: 'Slots are held for 1 hour. Upload your payment receipt to confirm.',
        color: 'success'
      });
    }
  } catch (e: unknown) {
    const err = e as { data?: { message?: string }; status?: number };
    bookingError.value =
      err?.status === 409
        ? 'One or more time slots were just taken. Please update your selection and try again.'
        : (err?.data?.message ?? 'Booking failed. Please try again.');
  } finally {
    isBooking.value = false;
  }
}

async function handleResendCode() {
  const firstGroup = groups.value[0];
  if (!firstGroup) return;
  isSendingCode.value = true;
  sendError.value = null;
  try {
    await sendGuestVerificationCode(props.hubId, firstGroup.court.id, email.value);
    toast.add({ title: 'Code resent', description: 'Check your email for a new code.', color: 'info' });
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } };
    sendError.value = err?.data?.message ?? 'Failed to resend code.';
  } finally {
    isSendingCode.value = false;
  }
}

function goBackToDetails() {
  step.value = 'details';
  otp.value = '';
  bookingError.value = null;
}

function resetForm() {
  step.value = 'details';
  guestName.value = '';
  email.value = '';
  guestPhone.value = '';
  otp.value = '';
  sendError.value = null;
  bookingError.value = null;
  selectedPaymentMethod.value = hubPaymentMethods.value.length === 1 ? hubPaymentMethods.value[0]! as 'pay_on_site' | 'digital_bank' : null;
}

function handleClose() {
  emit('update:open', false);
  resetForm();
}
</script>

<template>
  <AppModal
    :open="open"
    title="Book as Guest"
    :ui="{ content: 'max-w-lg' }"
    @update:open="handleClose"
  >
    <template #body>
      <!-- Oversized range warning -->
      <UAlert
        v-if="hasOversizedRange"
        color="error"
        variant="soft"
        icon="i-heroicons-exclamation-triangle"
        title="Selection too long"
        :description="`Guest bookings are limited to a maximum of ${guestMaxHours} hour${guestMaxHours !== 1 ? 's' : ''} per contiguous time range. Please adjust your selection.`"
        class="mb-4"
      />

      <!-- Booking summary chips -->
      <div v-if="groups.length > 0" class="mb-4 space-y-2">
        <div
          v-for="(group, gi) in groups"
          :key="gi"
          class="rounded-lg border border-[var(--aktiv-border)] bg-[var(--aktiv-background)] px-3 py-2.5"
        >
          <div class="mb-1.5 flex items-center justify-between text-sm">
            <span class="font-semibold text-[var(--aktiv-ink)]">{{ group.court.name }}</span>
            <span class="text-[var(--aktiv-muted)]">{{ group.dateLabel }}</span>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="(range, ri) in group.ranges"
              :key="ri"
              class="inline-flex items-center gap-1 rounded-md border border-[var(--aktiv-border)] px-2 py-0.5 text-xs text-[var(--aktiv-ink)]"
            >
              <UIcon name="i-heroicons-clock" class="h-3 w-3 text-[var(--aktiv-muted)]" />
              {{ range.label }}
            </span>
          </div>
          <div v-if="groupVoucherSubtotal(group) !== null" class="mt-1.5 text-right text-xs text-[var(--aktiv-muted)]">
            {{ group.totalHours }}hr × ₱{{ group.pricePerHour }}/hr =
            <strong class="text-[var(--aktiv-ink)]">
              <template v-if="appliedVoucherPreview && groupVoucherOriginalSubtotal(group) !== null">
                <span class="mr-1 font-normal line-through text-[var(--aktiv-muted)]">
                  ₱{{ formatPrice(groupVoucherOriginalSubtotal(group)!) }}
                </span>
              </template>
              ₱{{ formatPrice(groupVoucherSubtotal(group)!) }}
            </strong>
          </div>
        </div>

        <div v-if="displayedGrandTotal > 0" class="flex items-center justify-between pt-1 text-sm">
          <span class="font-semibold text-[var(--aktiv-ink)]">Total</span>
          <div class="text-right">
            <p
              v-if="displayedOriginalTotal !== null && appliedVoucherPreview"
              class="text-xs text-[var(--aktiv-muted)] line-through"
            >
              ₱{{ formatPrice(displayedOriginalTotal) }}
            </p>
            <span class="font-black text-[var(--aktiv-ink)]">₱{{ formatPrice(displayedGrandTotal) }}</span>
          </div>
        </div>

        <div
          v-if="appliedDiscountMeta"
          class="mt-2 rounded-xl border border-[#bbf7d0] bg-[#f0fdf4] px-3 py-2 text-sm text-[#166534]"
        >
          <p class="font-semibold">{{ appliedDiscountMeta.label }} applied</p>
          <p>
            Code: {{ props.voucherCode }}
            <span v-if="appliedVoucherPreview">
              · Saved ₱{{ formatPrice(appliedVoucherPreview.summary.discount_amount) }}
            </span>
            <span v-if="appliedDiscountMeta.overrides_promo">
              · This voucher overrides any active promo.
            </span>
          </p>
        </div>
      </div>

      <!-- ── Step 1: Details ──────────────────────────────────── -->
      <template v-if="step === 'details'">
        <div class="space-y-3">
          <UFormField label="Full Name" required>
            <UInput v-model="guestName" placeholder="Juan dela Cruz" class="w-full" />
          </UFormField>

          <UFormField label="Email Address" required>
            <UInput v-model="email" type="email" placeholder="you@example.com" class="w-full" />
          </UFormField>

          <UFormField label="Phone" required>
            <UInput v-model="guestPhone" type="tel" placeholder="+63 917 000 0000" class="w-full" />
          </UFormField>

          <!-- Payment method selector -->
          <BookingPaymentMethodSelector
            v-model="selectedPaymentMethod"
            :hub="hub"
          />

          <!-- Email note -->
          <UAlert
            color="info"
            variant="soft"
            icon="i-heroicons-envelope"
            description="Your email is required for verification and to avoid spam bookings. A 6-digit code will be sent to confirm your identity."
          />

          <UAlert
            v-if="sendError"
            color="error"
            variant="soft"
            :title="sendError"
          />
        </div>
      </template>

      <!-- ── Step 2: OTP entry ─────────────────────────────────── -->
      <template v-else>
        <div class="space-y-3">
          <p class="text-sm text-[var(--aktiv-muted)]">
            A 6-digit code was sent to
            <strong class="text-[var(--aktiv-ink)]">{{ email }}</strong>.
            Enter it below to complete your booking.
          </p>

          <UFormField label="Verification Code" required>
            <UInput
              v-model="otp"
              placeholder="000000"
              maxlength="6"
              class="w-full font-mono text-lg tracking-widest"
            />
          </UFormField>

          <div class="flex items-center gap-3 text-sm text-[var(--aktiv-muted)]">
            <button
              type="button"
              class="font-medium text-[#004e89] underline underline-offset-2"
              :disabled="isSendingCode"
              @click="goBackToDetails"
            >
              Change email
            </button>
            <span>·</span>
            <button
              type="button"
              class="font-medium text-[#004e89] underline underline-offset-2"
              :disabled="isSendingCode"
              @click="handleResendCode"
            >
              {{ isSendingCode ? 'Sending…' : 'Resend code' }}
            </button>
          </div>

          <UAlert
            v-if="sendError"
            color="error"
            variant="soft"
            :title="sendError"
          />

          <UAlert
            v-if="bookingError"
            color="error"
            variant="soft"
            :title="bookingError"
          />
        </div>
      </template>
    </template>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <template v-if="step === 'details'">
          <UButton color="neutral" variant="ghost" @click="handleClose">Cancel</UButton>
          <UButton
            color="primary"
            :loading="isSendingCode"
            :disabled="!guestName || !email || !guestPhone || hasOversizedRange || !selectedPaymentMethod"
            @click="handleSendCode"
          >
            Send Verification Code
          </UButton>
        </template>
        <template v-else>
          <UButton color="neutral" variant="ghost" @click="handleClose">Cancel</UButton>
          <UButton
            color="primary"
            :loading="isBooking"
            :disabled="otp.length !== 6"
            @click="handleVerifyAndBook"
          >
            Verify &amp; Book
          </UButton>
        </template>
      </div>
    </template>
  </AppModal>

  <BookingQrCodeModal
    v-model:open="isQrModalOpen"
    :booking="qrBooking"
    :court-name="qrCourtName"
  />
</template>
