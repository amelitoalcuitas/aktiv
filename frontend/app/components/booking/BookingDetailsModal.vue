<script setup lang="ts">
import { z } from 'zod';
import { CalendarDate } from '@internationalized/date';
import type { FormSubmitEvent } from '#ui/types';
import type { BookingDetail, BookingStatus } from '~/types/booking';
import type { Court } from '~/types/hub';

const props = defineProps<{
  open: boolean;
  booking: BookingDetail | null;
  courts: Court[];
  confirmLoading?: boolean;
  cancelLoading?: boolean;
  updateLoading?: boolean;
}>();

const formRef = useTemplateRef('formRef');

const emit = defineEmits<{
  'update:open': [boolean];
  'action-confirm': [BookingDetail];
  'action-reject': [BookingDetail];
  'action-cancel': [BookingDetail];
  'action-update': [{ id: number; data: any }];
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

// Schema for Editable Fields
const schema = z
  .object({
    court_id: z.number().min(1, 'Court is required'),
    date: z.date(),
    start_time: z.string().min(1, 'Start time is required'),
    end_time: z.string().min(1, 'End time is required')
  })
  .refine(
    (data) => {
      const parse = (t: string) => {
        const [h, m] = t.split(':').map(Number);
        return (h || 0) * 60 + (m || 0);
      };
      return parse(data.end_time) > parse(data.start_time);
    },
    { message: 'End time must be after start time', path: ['end_time'] }
  );

type Schema = z.infer<typeof schema>;
const state = reactive<Schema>({
  court_id: 0,
  date: new Date(),
  start_time: '',
  end_time: ''
});

// Initialize form matching the booking prop
watch(
  () => props.booking,
  (b) => {
    if (b) {
      state.court_id = b.court_id;
      const start = new Date(b.start_time);
      state.date = start;
      state.start_time = `${String(start.getHours()).padStart(2, '0')}:00`;
      const end = new Date(b.end_time);
      state.end_time = `${String(end.getHours()).padStart(2, '0')}:00`;
    }
  },
  { immediate: true }
);

// Form Select Options
const courtOptions = computed(() =>
  props.courts.map((c) => ({ label: c.name, value: c.id }))
);
const timeOptions = computed(() => {
  const opts = [];
  for (let h = 5; h <= 23; h++) {
    const hStr = String(h).padStart(2, '0');
    let label: string;
    if (h < 12) label = `${h}:00 AM`;
    else if (h === 12) label = '12:00 PM';
    else label = `${h - 12}:00 PM`;
    opts.push({ label, value: `${hStr}:00` });
  }
  return opts;
});

async function onSubmit(event: FormSubmitEvent<Schema>) {
  if (!props.booking) return;

  const { date, start_time, end_time, court_id } = event.data;

  const y = date.getFullYear();
  const mo = date.getMonth();
  const d = date.getDate();

  const [startH, startM] = start_time.split(':').map(Number);
  const startDt = new Date(y, mo, d);
  startDt.setHours(startH || 0, startM || 0, 0, 0);

  const [endH, endM] = end_time.split(':').map(Number);
  const endDt = new Date(y, mo, d);
  endDt.setHours(endH || 0, endM || 0, 0, 0);

  emit('action-update', {
    id: props.booking.id,
    data: {
      court_id,
      start_time: startDt.toISOString(),
      end_time: endDt.toISOString()
    }
  });
}

// Helper formats
function customerLabel(b: BookingDetail | null): string {
  if (!b) return 'Unknown';
  if (b.booked_by_user) return b.booked_by_user.name;
  if (b.guest_name) return `${b.guest_name} (guest)`;
  return 'Unknown';
}

function statusColor(
  status?: BookingStatus
): 'warning' | 'success' | 'error' | 'neutral' | 'primary' {
  switch (status) {
    case 'pending_payment':
      return 'warning';
    case 'payment_sent':
      return 'primary';
    case 'confirmed':
      return 'success';
    case 'cancelled':
      return 'error';
    case 'completed':
      return 'neutral';
    default:
      return 'neutral';
  }
}

function statusLabel(status?: BookingStatus): string {
  switch (status) {
    case 'pending_payment':
      return 'Pending Payment';
    case 'payment_sent':
      return 'Receipt Sent';
    case 'confirmed':
      return 'Confirmed';
    case 'cancelled':
      return 'Cancelled';
    case 'completed':
      return 'Completed';
    default:
      return status ?? '';
  }
}

const isCancellable = computed(() => {
  if (!props.booking) return false;
  return !['cancelled', 'completed', 'confirmed'].includes(
    props.booking.status
  );
});

const isConfirmable = computed(() => {
  if (!props.booking) return false;
  return (
    props.booking.status === 'payment_sent' ||
    props.booking.status === 'pending_payment'
  );
});

// For Date picker formatting
const dateString = computed(() => {
  return state.date.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
});

const calendarDate = computed({
  get() {
    const d = state.date;
    return new CalendarDate(d.getFullYear(), d.getMonth() + 1, d.getDate());
  },
  set(val: CalendarDate) {
    state.date = new Date(val.year, val.month - 1, val.day);
  }
});
</script>

<template>
  <AppModal v-model:open="isOpen" title="Booking Details & Edit">
    <template #body>
      <div v-if="booking" class="space-y-4">
        <!-- Persistent Read-Only Status & Info -->
        <div
          class="rounded-xl border border-[#dbe4ef] bg-[#f8fafc] p-4 text-sm"
        >
          <div class="mb-3 flex items-center justify-between">
            <span class="text-[#64748b]">Status</span>
            <UBadge
              :label="statusLabel(booking.status)"
              :color="statusColor(booking.status)"
              variant="subtle"
            />
          </div>
          <div class="flex justify-between">
            <span class="text-[#64748b]">Customer</span>
            <span class="font-medium text-[#0f1728]">{{
              customerLabel(booking)
            }}</span>
          </div>
          <div
            v-if="booking.booked_by_user?.email || booking.guest_email"
            class="mt-2 flex justify-between"
          >
            <span class="text-[#64748b]">Email</span>
            <a
              :href="`mailto:${booking.booked_by_user?.email ?? booking.guest_email}`"
              class="font-medium text-[#004e89] hover:underline"
            >
              {{ booking.booked_by_user?.email ?? booking.guest_email }}
            </a>
          </div>
          <div
            v-if="booking.booked_by_user?.phone || booking.guest_phone"
            class="mt-2 flex justify-between"
          >
            <span class="text-[#64748b]">Phone</span>
            <a
              :href="`tel:${booking.booked_by_user?.phone ?? booking.guest_phone}`"
              class="font-medium text-[#004e89] hover:underline"
            >
              {{ booking.booked_by_user?.phone ?? booking.guest_phone }}
            </a>
          </div>
          <div
            v-if="booking.payment_note && booking.status === 'pending_payment'"
            class="mt-3 flex gap-2 rounded bg-[#fef9c3] p-2 text-[#92400e]"
          >
            <UIcon
              name="i-heroicons-information-circle"
              class="h-5 w-5 shrink-0"
            />
            <span class="text-sm">{{ booking.payment_note }}</span>
          </div>
        </div>

        <!-- Editable Form Elements -->
        <UForm
          ref="formRef"
          :schema="schema"
          :state="state"
          @submit="onSubmit"
          class="space-y-4"
        >
          <UFormField label="Court" name="court_id">
            <USelectMenu
              v-model="state.court_id"
              :items="courtOptions"
              value-key="value"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Date" name="date">
            <UPopover :popper="{ placement: 'bottom-start' }" class="w-full">
              <UButton
                color="neutral"
                variant="outline"
                icon="i-heroicons-calendar-days"
                class="w-full justify-start"
              >
                {{ dateString }}
              </UButton>
              <template #content>
                <div class="p-2">
                  <UCalendar v-model="calendarDate" class="p-0" />
                </div>
              </template>
            </UPopover>
          </UFormField>

          <div class="grid grid-cols-2 gap-4">
            <UFormField label="Start Time" name="start_time">
              <USelectMenu
                v-model="state.start_time"
                :items="timeOptions"
                value-key="value"
                class="w-full"
              />
            </UFormField>

            <UFormField label="End Time" name="end_time">
              <USelectMenu
                v-model="state.end_time"
                :items="timeOptions"
                value-key="value"
                class="w-full"
              />
            </UFormField>
          </div>

          <!-- Receipt Image -->
          <div
            v-if="booking.receipt_image_url"
            class="rounded-xl border border-[#dbe4ef] p-4 mt-6"
          >
            <h4 class="mb-2 text-sm font-medium text-[#0f1728]">
              Receipt Image
            </h4>
            <AppImageViewer
              :src="booking.receipt_image_url"
              alt="Receipt"
              image-class="max-h-64 w-full rounded-lg object-contain bg-gray-50 border border-[#dbe4ef]"
            />
          </div>

          <!-- Confirm / Reject alert -->
          <UAlert
            v-if="isConfirmable"
            title="Awaiting your approval"
            description="This booking is pending payment confirmation. Please review and confirm or reject."
            color="warning"
            variant="subtle"
            :actions="[
              {
                label: 'Reject',
                color: 'error',
                variant: 'outline',
                icon: 'i-heroicons-x-circle',
                onClick: () => emit('action-reject', booking!)
              },
              {
                label: 'Confirm',
                color: 'secondary',
                icon: 'i-heroicons-check-circle',
                loading: confirmLoading,
                onClick: () => emit('action-confirm', booking!)
              }
            ]"
          />

          <!-- Note: The form submit will be attached to an invisible button hidden down below. We place the visual button in footer. -->
          <button
            type="submit"
            id="booking-details-modal-submit"
            class="hidden"
          ></button>
        </UForm>
      </div>
      <div v-else class="py-8 text-center text-sm text-[#64748b]">
        No booking selected.
      </div>
    </template>

    <template #footer>
      <div class="flex w-full flex-wrap justify-between gap-2">
        <div>
          <UButton
            v-if="isCancellable"
            color="error"
            variant="ghost"
            icon="i-heroicons-x-mark"
            :loading="cancelLoading"
            @click="emit('action-cancel', booking!)"
          >
            Cancel Booking
          </UButton>
        </div>
        <div class="flex gap-2">
          <UButton
            color="primary"
            variant="solid"
            icon="i-heroicons-document-check"
            :loading="updateLoading"
            @click="formRef?.submit()"
          >
            Save Changes
          </UButton>
        </div>
      </div>
    </template>
  </AppModal>
</template>
