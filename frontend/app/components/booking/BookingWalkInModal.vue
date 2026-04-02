<script setup lang="ts">
import { z } from 'zod';
import type { FormSubmitEvent } from '#ui/types';
import type { Court, OperatingHoursEntry } from '~/types/hub';
import type { BookingDetail } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';
import { useOwnerBookings } from '~/composables/useOwnerBookings';
import { useOwnerOpenPlay } from '~/composables/useOwnerOpenPlay';

const props = defineProps<{
  open: boolean;
  hubId?: string;
  courts: Court[];
  initialDate?: string;
  initialHour?: number;
  initialCourtId?: string;
  operatingHours?: OperatingHoursEntry[];
  mode?: 'both' | 'walkin' | 'openplay';
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  'created': [BookingDetail];
  'openplay:created': [OpenPlaySession];
}>();

const { createWalkIn, searchUsers } = useOwnerBookings();
const { createSession } = useOwnerOpenPlay();
const toast = useToast();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

const resolvedMode = computed(() => props.mode ?? 'both');
const defaultBookingMode = computed<'walkin' | 'openplay'>(() =>
  resolvedMode.value === 'openplay' ? 'openplay' : 'walkin'
);
const showModeToggle = computed(() => resolvedMode.value === 'both');

// ── Mode toggle ──────────────────────────────────────────────
const bookingMode = ref<'walkin' | 'openplay'>(defaultBookingMode.value);

const loading = ref(false);
const formRef = useTemplateRef('formRef');

// ── Walk-in schema ───────────────────────────────────────────
const walkInSchema = z
  .object({
    courtId: z.string({ message: 'Select a court.' }).min(1, 'Select a court.'),
    date: z.string().min(1, 'Select a date.'),
    startHour: z.number(),
    endHour: z.number(),
    customerMode: z.enum(['guest', 'registered']),
    bookedBy: z.string().optional(),
    guestName: z.string().optional(),
    guestPhone: z.string().optional(),
    guestEmail: z
      .string()
      .email('Enter a valid email.')
      .optional()
      .or(z.literal(''))
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

// ── Open play schema ─────────────────────────────────────────
const openPlaySchema = z.object({
  title: z
    .string({ message: 'Enter a title.' })
    .trim()
    .min(1, 'Enter a title.')
    .max(120, 'Max 120 characters.'),
  courtId: z.string({ message: 'Select a court.' }).min(1, 'Select a court.'),
  date: z.string().min(1, 'Select a date.'),
  startHour: z.number(),
  endHour: z.number(),
  maxPlayers: z
    .number({ invalid_type_error: 'Enter a number.' })
    .int()
    .min(2, 'Minimum 2 players.'),
  pricePerPlayer: z
    .number({ invalid_type_error: 'Enter a number.' })
    .min(0, 'Price must be 0 or more.'),
  description: z
    .string()
    .max(500, 'Max 500 characters.')
    .optional()
    .or(z.literal('')),
  guestsCanJoin: z.boolean()
});

const activeSchema = computed(() =>
  bookingMode.value === 'openplay' ? openPlaySchema : walkInSchema
);

type WalkInSchema = z.infer<typeof walkInSchema>;
type OpenPlaySchema = z.infer<typeof openPlaySchema>;

// ── Shared form state ────────────────────────────────────────
const walkInForm = reactive<WalkInSchema>({
  courtId: undefined as any,
  date: '',
  startHour: 8,
  endHour: 9,
  customerMode: 'guest',
  bookedBy: undefined,
  guestName: '',
  guestPhone: '',
  guestEmail: ''
});

const openPlayForm = reactive<OpenPlaySchema>({
  title: '',
  courtId: undefined as any,
  date: '',
  startHour: 8,
  endHour: 9,
  maxPlayers: 4,
  pricePerPlayer: 0,
  description: '',
  guestsCanJoin: false
});

const activeForm = computed(() =>
  bookingMode.value === 'openplay' ? openPlayForm : walkInForm
);

// ── User search ──────────────────────────────────────────────
const userSearchQuery = ref('');
const userSearchResults = ref<
  {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string | null;
    avatar_url: string | null;
  }[]
>([]);
const userSearchLoading = ref(false);
const selectedUser = ref<{
  id: string;
  first_name: string;
  last_name: string;
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
    label: `${u.first_name} ${u.last_name}`.trim(),
    value: u.id,
    email: u.email
  }))
);

function onUserSelect(
  item: { label: string; value: string; email: string } | null
) {
  if (!item) {
    selectedUser.value = null;
    walkInForm.bookedBy = undefined;
    return;
  }
  const found = userSearchResults.value.find((u) => u.id === item.value);
  selectedUser.value = {
    id: item.value,
    first_name: found?.first_name ?? item.label,
    last_name: found?.last_name ?? '',
    email: item.email
  };
  walkInForm.bookedBy = item.value;
  userSearchQuery.value = '';
}

function clearSelectedUser() {
  selectedUser.value = null;
  walkInForm.bookedBy = undefined;
  userSearchQuery.value = '';
  userSearchResults.value = [];
}

// ── Court options ────────────────────────────────────────────
const walkInCourtOptions = computed(() =>
  props.courts.map((c) => ({ label: c.name, value: c.id }))
);

// ── Time helpers ─────────────────────────────────────────────
const sharedStartHour = computed({
  get: () =>
    bookingMode.value === 'openplay'
      ? openPlayForm.startHour
      : walkInForm.startHour,
  set: (v) => {
    if (bookingMode.value === 'openplay') openPlayForm.startHour = v;
    else walkInForm.startHour = v;
  }
});

watch(
  () => walkInForm.startHour,
  () => {
    if (walkInForm.endHour <= walkInForm.startHour) {
      walkInForm.endHour = walkInForm.startHour + 1;
    }
  }
);

watch(
  () => openPlayForm.startHour,
  () => {
    if (openPlayForm.endHour <= openPlayForm.startHour) {
      openPlayForm.endHour = openPlayForm.startHour + 1;
    }
  }
);

const dateObj = computed({
  get() {
    const d =
      bookingMode.value === 'openplay' ? openPlayForm.date : walkInForm.date;
    if (!d) return new Date();
    const [y, mo, day] = d.split('-').map(Number);
    return new Date(y!, mo! - 1, day!);
  },
  set(val: Date) {
    const str = `${val.getFullYear()}-${String(val.getMonth() + 1).padStart(2, '0')}-${String(val.getDate()).padStart(2, '0')}`;
    if (bookingMode.value === 'openplay') openPlayForm.date = str;
    else walkInForm.date = str;
  }
});

const currentDate = computed(() =>
  bookingMode.value === 'openplay' ? openPlayForm.date : walkInForm.date
);

const isPastSlot = computed(() => {
  if (!currentDate.value) return false;
  const now = new Date();
  const [y, mo, d] = currentDate.value.split('-').map(Number);
  const form = bookingMode.value === 'openplay' ? openPlayForm : walkInForm;
  const slotStart = new Date(y!, mo! - 1, d!);
  slotStart.setHours(form.startHour, 0, 0, 0);
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
  const range = getOperatingRange(currentDate.value);
  const openHour = range?.openHour ?? 6;
  const closeHour = range?.closeHour ?? 23;
  return Array.from({ length: closeHour - openHour }, (_, i) => {
    const h = openHour + i;
    return { label: formatHourLabel(h), value: h };
  });
});

const endHourOptions = computed(() => {
  const range = getOperatingRange(currentDate.value);
  const closeHour = range?.closeHour ?? 23;
  const form = bookingMode.value === 'openplay' ? openPlayForm : walkInForm;
  const min = form.startHour + 1;
  const count = closeHour - min + 1;
  if (count <= 0) return [];
  return Array.from({ length: count }, (_, i) => {
    const h = min + i;
    return { label: formatHourLabel(h), value: h };
  });
});

// ── Reset ────────────────────────────────────────────────────
function resetForm() {
  bookingMode.value = defaultBookingMode.value;

  const courtId = (props.initialCourtId ??
    props.courts[0]?.id ??
    undefined) as any;
  const d = new Date();
  const dateStr =
    props.initialDate ||
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const startHour =
    props.initialHour ?? startTimeHourOptions.value[0]?.value ?? 8;

  walkInForm.courtId = courtId;
  walkInForm.date = dateStr;
  walkInForm.startHour = startHour;
  walkInForm.endHour = startHour + 1;
  walkInForm.customerMode = 'guest';
  walkInForm.bookedBy = undefined;
  walkInForm.guestName = '';
  walkInForm.guestPhone = '';
  walkInForm.guestEmail = '';
  selectedUser.value = null;
  userSearchQuery.value = '';
  userSearchResults.value = [];

  openPlayForm.courtId = courtId;
  openPlayForm.title = '';
  openPlayForm.date = dateStr;
  openPlayForm.startHour = startHour;
  openPlayForm.endHour = startHour + 1;
  openPlayForm.maxPlayers = 4;
  openPlayForm.pricePerPlayer = 0;
  openPlayForm.description = '';
  openPlayForm.guestsCanJoin = false;
}

watch(
  () => props.open,
  (val) => {
    if (val) resetForm();
  }
);

// Sync court + date + time between modes when switching
watch(bookingMode, (mode) => {
  if (mode === 'openplay') {
    openPlayForm.courtId = walkInForm.courtId;
    openPlayForm.date = walkInForm.date;
    openPlayForm.startHour = walkInForm.startHour;
    openPlayForm.endHour = walkInForm.endHour;
  } else {
    walkInForm.courtId = openPlayForm.courtId;
    walkInForm.date = openPlayForm.date;
    walkInForm.startHour = openPlayForm.startHour;
    walkInForm.endHour = openPlayForm.endHour;
  }
});

// ── Submit ────────────────────────────────────────────────────
async function onSubmitWalkIn(event: FormSubmitEvent<WalkInSchema>) {
  if (!props.hubId) return;
  const {
    date,
    startHour,
    endHour,
    courtId,
    customerMode,
    bookedBy,
    guestName,
    guestPhone,
    guestEmail
  } = event.data;

  const [y, mo, day] = date.split('-').map(Number);
  const startDt = new Date(y!, mo! - 1, day!);
  startDt.setHours(startHour, 0, 0, 0);
  const endDt = new Date(y!, mo! - 1, day!);
  endDt.setHours(endHour, 0, 0, 0);

  loading.value = true;
  try {
    const booking = await createWalkIn(props.hubId, courtId, {
      court_id: courtId,
      start_time: startDt.toISOString(),
      end_time: endDt.toISOString(),
      session_type: 'private',
      booked_by: customerMode === 'registered' ? bookedBy : null,
      guest_name: customerMode === 'guest' ? guestName?.trim() || null : null,
      guest_phone: customerMode === 'guest' ? guestPhone?.trim() || null : null,
      guest_email: customerMode === 'guest' ? guestEmail?.trim() || null : null
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
    loading.value = false;
  }
}

async function onSubmitOpenPlay(event: FormSubmitEvent<OpenPlaySchema>) {
  if (!props.hubId) return;
  const {
    date,
    startHour,
    endHour,
    title,
    courtId,
    maxPlayers,
    pricePerPlayer,
    description,
    guestsCanJoin
  } = event.data;

  const [y, mo, day] = date.split('-').map(Number);
  const startDt = new Date(y!, mo! - 1, day!);
  startDt.setHours(startHour, 0, 0, 0);
  const endDt = new Date(y!, mo! - 1, day!);
  endDt.setHours(endHour, 0, 0, 0);

  loading.value = true;
  try {
    const session = await createSession(props.hubId, {
      title: title.trim(),
      court_id: courtId,
      start_time: startDt.toISOString(),
      end_time: endDt.toISOString(),
      max_players: maxPlayers,
      price_per_player: pricePerPlayer,
      description: description?.trim() || null,
      guests_can_join: guestsCanJoin
    });
    emit('openplay:created', session);
    isOpen.value = false;
    toast.add({ title: 'Open play session created', color: 'success' });
  } catch (err: unknown) {
    const msg =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to create open play session.';
    if (
      msg.toLowerCase().includes('already booked') ||
      msg.toLowerCase().includes('time slot')
    ) {
      formRef.value?.setErrors([{ name: 'date', message: msg }]);
    } else {
      toast.add({ title: msg, color: 'error' });
    }
  } finally {
    loading.value = false;
  }
}

function onSubmit(event: FormSubmitEvent<WalkInSchema | OpenPlaySchema>) {
  if (bookingMode.value === 'openplay') {
    onSubmitOpenPlay(event as FormSubmitEvent<OpenPlaySchema>);
  } else {
    onSubmitWalkIn(event as FormSubmitEvent<WalkInSchema>);
  }
}
</script>

<template>
  <AppModal
    v-model:open="isOpen"
    :title="
      resolvedMode === 'openplay'
        ? 'Create Open Play'
        : resolvedMode === 'walkin'
          ? 'Add Walk-in'
          : 'Add Booking'
    "
    :ui="{ content: 'sm:max-w-xl' }"
    :confirm="
      resolvedMode === 'openplay' || bookingMode === 'openplay'
        ? 'Create Open Play Session'
        : 'Confirm Walk-in'
    "
    :confirm-loading="loading"
    @confirm="formRef?.submit()"
  >
    <template #body>
      <!-- Mode toggle -->
      <div v-if="showModeToggle" class="mb-4 flex gap-2">
        <UButton
          size="sm"
          :variant="bookingMode === 'walkin' ? 'solid' : 'outline'"
          :color="bookingMode === 'walkin' ? 'primary' : 'neutral'"
          @click="bookingMode = 'walkin'"
        >
          Walk-in
        </UButton>
        <UButton
          size="sm"
          :variant="bookingMode === 'openplay' ? 'solid' : 'outline'"
          :color="bookingMode === 'openplay' ? 'primary' : 'neutral'"
          @click="bookingMode = 'openplay'"
        >
          Open Play
        </UButton>
      </div>

      <!-- Walk-in form -->
      <UForm
        v-if="bookingMode === 'walkin'"
        ref="formRef"
        :schema="walkInSchema"
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
            <AppDatePicker
              v-model="dateObj"
              variant="nav"
              display="field"
              :allow-past="false"
              :label="
                dateObj.toLocaleDateString('en-PH', {
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric'
                })
              "
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
            <UFormField name="guestEmail" class="mt-2">
              <UInput
                v-model="walkInForm.guestEmail"
                type="email"
                placeholder="Email address (optional)"
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
                  {{
                    `${selectedUser.first_name} ${selectedUser.last_name}`.trim()
                  }}
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

        <button type="submit" class="hidden" />
      </UForm>

      <!-- Open Play form -->
      <UForm
        v-else
        ref="formRef"
        :schema="openPlaySchema"
        :state="openPlayForm"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField label="Title" name="title">
          <UInput
            v-model="openPlayForm.title"
            placeholder="e.g. Friday Night Doubles"
            class="w-full"
          />
        </UFormField>

        <!-- Court -->
        <UFormField label="Court" name="courtId">
          <USelect
            v-model="openPlayForm.courtId"
            :items="walkInCourtOptions"
            class="w-full"
          />
        </UFormField>

        <!-- Date + time -->
        <div class="grid grid-cols-3 gap-3">
          <UFormField label="Date" name="date" class="col-span-1">
            <AppDatePicker
              v-model="dateObj"
              variant="nav"
              display="field"
              :allow-past="false"
              :label="
                dateObj.toLocaleDateString('en-PH', {
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric'
                })
              "
            />
          </UFormField>
          <UFormField label="Start" name="startHour">
            <USelect
              v-model="openPlayForm.startHour"
              :items="startTimeHourOptions"
              class="w-full"
            />
          </UFormField>
          <UFormField label="End" name="endHour">
            <USelect
              v-model="openPlayForm.endHour"
              :items="endHourOptions"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Max players + Price -->
        <div class="grid grid-cols-2 gap-3">
          <UFormField label="Max Players" name="maxPlayers">
            <UInput
              v-model.number="openPlayForm.maxPlayers"
              type="number"
              :min="2"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Price per Player (₱)" name="pricePerPlayer">
            <UInput
              v-model.number="openPlayForm.pricePerPlayer"
              type="number"
              :min="0"
              step="0.01"
              placeholder="0 for free"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Notes -->
        <UFormField label="Description (optional)" name="description">
          <UTextarea
            v-model="openPlayForm.description"
            placeholder="e.g. Bring your own racket and shuttlecocks"
            :rows="3"
            :maxlength="500"
            class="w-full"
          />
        </UFormField>

        <!-- Guests can join -->
        <div
          class="flex items-center justify-between rounded-xl border border-[#dbe4ef] bg-[#f8fafc] px-3 py-2.5"
        >
          <div>
            <p class="text-sm font-medium text-[#0f1728]">
              Allow guests to join
            </p>
            <p class="text-xs text-[#64748b]">
              Unregistered players can join via email verification
            </p>
          </div>
          <USwitch v-model="openPlayForm.guestsCanJoin" />
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

        <button type="submit" class="hidden" />
      </UForm>
    </template>
  </AppModal>
</template>
