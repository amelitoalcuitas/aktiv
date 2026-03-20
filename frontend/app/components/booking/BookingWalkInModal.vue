<script setup lang="ts">
import { z } from 'zod';
import type { FormSubmitEvent } from '#ui/types';
import type { Court, OperatingHoursEntry } from '~/types/hub';
import type { BookingDetail } from '~/types/booking';
import { useOwnerBookings } from '~/composables/useOwnerBookings';

const props = defineProps<{
  open: boolean;
  hubId?: number;
  courts: Court[];
  initialDate?: string;
  initialHour?: number;
  initialCourtId?: number;
  operatingHours?: OperatingHoursEntry[];
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  'created': [BookingDetail];
}>();

const { createWalkIn, searchUsers } = useOwnerBookings();
const toast = useToast();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

const walkInLoading = ref(false);
const formRef = useTemplateRef('formRef');

// Schema
const schema = z
  .object({
    courtId: z.number({ message: 'Select a court.' }).min(1, 'Select a court.'),
    date: z.string().min(1, 'Select a date.'),
    startHour: z.number(),
    endHour: z.number(),
    customerMode: z.enum(['guest', 'registered']),
    bookedBy: z.number().optional(),
    guestName: z.string().optional(),
    guestPhone: z.string().optional()
  })
  .superRefine((data, ctx) => {
    if (data.customerMode === 'registered' && !data.bookedBy) {
      ctx.addIssue({
        code: 'custom',
        message: 'Search and select a registered user.',
        path: ['bookedBy']
      });
    }
    if (data.customerMode === 'guest' && !data.guestName?.trim()) {
      ctx.addIssue({
        code: 'custom',
        message: 'Guest name is required.',
        path: ['guestName']
      });
    }
  });

type Schema = z.infer<typeof schema>;

const walkInForm = reactive<Schema>({
  courtId: undefined as any,
  date: '',
  startHour: 8,
  endHour: 9,
  customerMode: 'guest',
  bookedBy: undefined,
  guestName: '',
  guestPhone: ''
});

// User search
const userSearchQuery = ref('');
const userSearchResults = ref<
  {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
  }[]
>([]);
const userSearchLoading = ref(false);
const selectedUser = ref<{
  id: number;
  name: string;
  email: string;
} | null>(null);

let searchDebounce: ReturnType<typeof setTimeout>;
watch(userSearchQuery, (q) => {
  clearTimeout(searchDebounce);
  if (!q.trim()) {
    userSearchResults.value = [];
    userSearchLoading.value = false;
    return;
  }
  userSearchLoading.value = true;
  searchDebounce = setTimeout(async () => {
    try {
      userSearchResults.value = await searchUsers(q.trim());
    } catch {
      userSearchResults.value = [];
    } finally {
      userSearchLoading.value = false;
    }
  }, 350);
});

const userSelectItems = computed(() =>
  userSearchResults.value.map((u) => ({
    label: u.name,
    value: u.id,
    email: u.email
  }))
);

function onUserSelect(
  item: { label: string; value: number; email: string } | null
) {
  if (!item) {
    selectedUser.value = null;
    walkInForm.bookedBy = undefined;
    return;
  }
  selectedUser.value = { id: item.value, name: item.label, email: item.email };
  walkInForm.bookedBy = item.value;
  userSearchQuery.value = '';
}

function clearSelectedUser() {
  selectedUser.value = null;
  walkInForm.bookedBy = undefined;
  userSearchQuery.value = '';
  userSearchResults.value = [];
}

const walkInCourtOptions = computed(() =>
  props.courts.map((c) => ({ label: c.name, value: c.id }))
);

watch(
  () => walkInForm.startHour,
  () => {
    if (walkInForm.endHour <= walkInForm.startHour) {
      walkInForm.endHour = walkInForm.startHour + 1;
    }
  }
);

const todayStr = computed(() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const isPastSlot = computed(() => {
  if (!walkInForm.date) return false;
  const now = new Date();
  const [y, mo, d] = walkInForm.date.split('-').map(Number);
  const slotStart = new Date(y!, mo! - 1, d!);
  slotStart.setHours(walkInForm.startHour, 0, 0, 0);
  return slotStart < now;
});

function formatHourLabel(h: number): string {
  if (h === 12) return '12:00 PM';
  if (h < 12) return `${h}:00 AM`;
  return `${h - 12}:00 PM`;
}

function getOperatingRange(
  date: string
): { openHour: number; closeHour: number } | null {
  const oh = props.operatingHours ?? [];
  if (!oh.length || !date) return null;
  const [y, mo, d] = date.split('-').map(Number);
  const dow = new Date(y!, mo! - 1, d!).getDay();
  const entry = oh.find((e) => e.day_of_week === dow);
  if (!entry || entry.is_closed) return null;
  return {
    openHour: parseInt(entry.opens_at.split(':')[0]!, 10),
    closeHour: parseInt(entry.closes_at.split(':')[0]!, 10)
  };
}

const startTimeHourOptions = computed(() => {
  const range = getOperatingRange(walkInForm.date);
  const openHour = range?.openHour ?? 6;
  const closeHour = range?.closeHour ?? 23;
  return Array.from({ length: closeHour - openHour }, (_, i) => {
    const h = openHour + i;
    return { label: formatHourLabel(h), value: h };
  });
});

const endHourOptions = computed(() => {
  const range = getOperatingRange(walkInForm.date);
  const closeHour = range?.closeHour ?? 23;
  const min = walkInForm.startHour + 1;
  const count = closeHour - min + 1;
  if (count <= 0) return [];
  return Array.from({ length: count }, (_, i) => {
    const h = min + i;
    return { label: formatHourLabel(h), value: h };
  });
});

function resetForm() {
  walkInForm.courtId = (props.initialCourtId ??
    props.courts[0]?.id ??
    undefined) as any;
  const d = new Date();
  walkInForm.date =
    props.initialDate ||
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  walkInForm.startHour =
    props.initialHour ?? startTimeHourOptions.value[0]?.value ?? 8;
  walkInForm.endHour = walkInForm.startHour + 1;
  walkInForm.customerMode = 'guest';
  walkInForm.bookedBy = undefined;
  walkInForm.guestName = '';
  walkInForm.guestPhone = '';
  selectedUser.value = null;
  userSearchQuery.value = '';
  userSearchResults.value = [];
}

watch(
  () => props.open,
  (val) => {
    if (val) resetForm();
  }
);

async function onSubmit(event: FormSubmitEvent<Schema>) {
  if (!props.hubId) return;
  const {
    date,
    startHour,
    endHour,
    courtId,
    customerMode,
    bookedBy,
    guestName,
    guestPhone
  } = event.data;

  const [y, mo, day] = date.split('-').map(Number);
  const startDt = new Date(y!, mo! - 1, day!);
  startDt.setHours(startHour, 0, 0, 0);
  const endDt = new Date(y!, mo! - 1, day!);
  endDt.setHours(endHour, 0, 0, 0);

  walkInLoading.value = true;
  try {
    const booking = await createWalkIn(props.hubId, courtId, {
      court_id: courtId,
      start_time: startDt.toISOString(),
      end_time: endDt.toISOString(),
      session_type: 'private',
      booked_by: customerMode === 'registered' ? bookedBy : null,
      guest_name: customerMode === 'guest' ? guestName?.trim() || null : null,
      guest_phone: customerMode === 'guest' ? guestPhone?.trim() || null : null
    });
    emit('created', booking);
    isOpen.value = false;
    toast.add({ title: 'Walk-in booking created', color: 'success' });
  } catch (err: unknown) {
    const msg =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to create walk-in booking.';
    if (msg.toLowerCase().includes('already booked')) {
      formRef.value?.setErrors([{ name: 'date', message: msg }]);
    } else {
      toast.add({ title: msg, color: 'error' });
    }
  } finally {
    walkInLoading.value = false;
  }
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    title="Add Walk-in Booking"
    :ui="{ content: 'sm:max-w-xl' }"
  >
    <template #body>
      <UForm
        ref="formRef"
        :schema="schema"
        :state="walkInForm"
        class="space-y-4"
        @submit="onSubmit"
      >
        <!-- Court -->
        <UFormField label="Court" name="courtId">
          <USelect
            v-model="walkInForm.courtId"
            :items="walkInCourtOptions"
            class="w-full"
          />
        </UFormField>

        <!-- Date + time -->
        <div class="grid grid-cols-3 gap-3">
          <UFormField label="Date" name="date" class="col-span-1">
            <UInput
              v-model="walkInForm.date"
              type="date"
              :min="todayStr"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Start" name="startHour">
            <USelect
              v-model="walkInForm.startHour"
              :items="startTimeHourOptions"
              class="w-full"
            />
          </UFormField>
          <UFormField label="End" name="endHour">
            <USelect
              v-model="walkInForm.endHour"
              :items="endHourOptions"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Customer -->
        <div>
          <p class="mb-1.5 text-sm font-medium text-[#0f1728]">Customer</p>
          <div class="mb-2 flex gap-2">
            <UButton
              size="sm"
              :variant="
                walkInForm.customerMode === 'guest' ? 'solid' : 'outline'
              "
              :color="
                walkInForm.customerMode === 'guest' ? 'primary' : 'neutral'
              "
              @click="walkInForm.customerMode = 'guest'"
            >
              Guest
            </UButton>
            <UButton
              size="sm"
              :variant="
                walkInForm.customerMode === 'registered' ? 'solid' : 'outline'
              "
              :color="
                walkInForm.customerMode === 'registered' ? 'primary' : 'neutral'
              "
              @click="walkInForm.customerMode = 'registered'"
            >
              Registered User
            </UButton>
          </div>

          <!-- Guest fields -->
          <template v-if="walkInForm.customerMode === 'guest'">
            <UFormField name="guestName">
              <UInput
                v-model="walkInForm.guestName"
                placeholder="Full name"
                class="w-full"
              />
            </UFormField>
            <UInput
              v-model="walkInForm.guestPhone"
              placeholder="Phone number (optional)"
              class="mt-2 w-full"
            />
          </template>

          <!-- Registered user search -->
          <template v-else>
            <UFormField name="bookedBy">
              <USelectMenu
                v-model:search-term="userSearchQuery"
                :items="userSelectItems"
                :loading="userSearchLoading"
                placeholder="Search by name, email, or phone…"
                icon="i-heroicons-magnifying-glass"
                class="w-full"
                @update:model-value="onUserSelect"
              >
                <template #empty>
                  <span class="text-sm text-[#64748b]">
                    {{
                      userSearchLoading
                        ? 'Searching…'
                        : userSearchQuery.trim()
                          ? 'No users found'
                          : 'Type to search…'
                    }}
                  </span>
                </template>
                <template #item="{ item }">
                  <span class="font-medium text-[#0f1728]">{{
                    item.label
                  }}</span>
                  <span class="ml-2 text-[#64748b]">{{ item.email }}</span>
                </template>
              </USelectMenu>
            </UFormField>
            <div
              v-if="selectedUser"
              class="mt-2 flex items-center justify-between rounded-xl border border-[#dbe4ef] bg-[#f8fafc] px-3 py-2"
            >
              <div>
                <p class="text-sm font-medium text-[#0f1728]">
                  {{ selectedUser.name }}
                </p>
                <p class="text-sm text-[#64748b]">{{ selectedUser.email }}</p>
              </div>
              <UButton
                icon="i-heroicons-x-mark"
                color="neutral"
                variant="ghost"
                @click="clearSelectedUser"
              />
            </div>
          </template>
        </div>

        <!-- Past slot warning -->
        <UAlert
          v-if="isPastSlot"
          color="warning"
          variant="subtle"
          icon="i-heroicons-exclamation-triangle"
          title="This time slot is in the past."
          description="Please confirm the date and time are correct before proceeding."
        />

        <!-- Hidden submit trigger -->
        <button type="submit" class="hidden" />
      </UForm>
    </template>
    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton color="neutral" variant="outline" @click="isOpen = false">
          Cancel
        </UButton>
        <UButton
          class="bg-[#004e89] hover:bg-[#003d6b]"
          :loading="walkInLoading"
          @click="formRef?.submit()"
        >
          Confirm Walk-in
        </UButton>
      </div>
    </template>
  </UModal>
</template>
