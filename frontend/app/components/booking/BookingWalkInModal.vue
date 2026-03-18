<script setup lang="ts">
import type { Court } from '~/types/hub';
import type { BookingDetail } from '~/types/booking';
import { useOwnerBookings } from '~/composables/useOwnerBookings';

const props = defineProps<{
  open: boolean;
  hubId?: number;
  courts: Court[];
  initialDate?: string;
  initialHour?: number;
  initialCourtId?: number;
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

const walkInForm = reactive({
  courtId: null as number | null,
  sport: '',
  date: '',
  startHour: 8,
  duration: 1,
  customerMode: 'guest' as 'registered' | 'guest',
  bookedBy: null as number | null,
  guestName: '',
  guestPhone: ''
});

const walkInErrors = reactive({
  court: '',
  sport: '',
  date: '',
  customer: ''
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
    return;
  }
  searchDebounce = setTimeout(async () => {
    userSearchLoading.value = true;
    try {
      userSearchResults.value = await searchUsers(q.trim());
    } catch {
      userSearchResults.value = [];
    } finally {
      userSearchLoading.value = false;
    }
  }, 350);
});

function selectUser(user: {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
}) {
  selectedUser.value = { id: user.id, name: user.name, email: user.email };
  walkInForm.bookedBy = user.id;
  userSearchResults.value = [];
  userSearchQuery.value = '';
}

function clearSelectedUser() {
  selectedUser.value = null;
  walkInForm.bookedBy = null;
}

const walkInCourtOptions = computed(() =>
  props.courts.map((c) => ({ label: c.name, value: c.id }))
);

const walkInSelectedCourt = computed(() =>
  props.courts.find((c) => c.id === walkInForm.courtId)
);

const walkInSportOptions = computed(() =>
  (walkInSelectedCourt.value?.sports ?? []).map((s) => ({
    label: s.charAt(0).toUpperCase() + s.slice(1),
    value: s
  }))
);

watch(
  () => walkInForm.courtId,
  () => {
    if (!walkInForm.sport || !walkInSelectedCourt.value?.sports.includes(walkInForm.sport)) {
      walkInForm.sport = walkInSportOptions.value[0]?.value ?? '';
    }
  }
);

const todayStr = computed(() => new Date().toISOString().slice(0, 10));

const startTimeHourOptions = Array.from({ length: 18 }, (_, i) => {
  const h = i + 6;
  const label =
    h === 12 ? '12:00 PM' : h < 12 ? `${h}:00 AM` : `${h - 12}:00 PM`;
  return { label, value: h };
});

const durationOptions = Array.from({ length: 8 }, (_, i) => ({
  label: `${i + 1} hour${i > 0 ? 's' : ''}`,
  value: i + 1
}));

function clearWalkInErrors() {
  walkInErrors.court = '';
  walkInErrors.sport = '';
  walkInErrors.date = '';
  walkInErrors.customer = '';
}

function resetForm() {
  clearWalkInErrors();
  walkInForm.courtId = props.initialCourtId ?? (props.courts[0]?.id ?? null);
  walkInForm.date = props.initialDate || new Date().toISOString().slice(0, 10);
  walkInForm.startHour = props.initialHour ?? 8;
  walkInForm.duration = 1;

  walkInForm.customerMode = 'guest';
  walkInForm.bookedBy = null;
  walkInForm.guestName = '';
  walkInForm.guestPhone = '';
  selectedUser.value = null;
  userSearchQuery.value = '';
  userSearchResults.value = [];
}

watch(() => props.open, (val) => {
  if (val) {
    resetForm();
  }
});

async function submitWalkIn() {
  if (!props.hubId) return;
  clearWalkInErrors();
  let valid = true;

  if (!walkInForm.courtId) {
    walkInErrors.court = 'Select a court.';
    valid = false;
  }
  if (!walkInForm.sport) {
    walkInErrors.sport = 'Select a sport.';
    valid = false;
  }
  if (!walkInForm.date) {
    walkInErrors.date = 'Select a date.';
    valid = false;
  }
  if (walkInForm.customerMode === 'registered' && !walkInForm.bookedBy) {
    walkInErrors.customer = 'Search and select a registered user.';
    valid = false;
  }
  if (walkInForm.customerMode === 'guest' && !walkInForm.guestName.trim()) {
    walkInErrors.customer = 'Guest name is required.';
    valid = false;
  }

  if (!valid) return;

  const [y, mo, day] = walkInForm.date.split('-').map(Number);
  const startDt = new Date(y!, mo! - 1, day!);
  startDt.setHours(walkInForm.startHour, 0, 0, 0);
  const endDt = new Date(startDt.getTime() + walkInForm.duration * 3_600_000);

  walkInLoading.value = true;
  try {
    const booking = await createWalkIn(
      props.hubId,
      walkInForm.courtId!,
      {
        court_id: walkInForm.courtId!,
        sport: walkInForm.sport,
        start_time: startDt.toISOString(),
        end_time: endDt.toISOString(),
        session_type: 'private',
        booked_by:
          walkInForm.customerMode === 'registered' ? walkInForm.bookedBy : null,
        guest_name:
          walkInForm.customerMode === 'guest'
            ? walkInForm.guestName.trim() || null
            : null,
        guest_phone:
          walkInForm.customerMode === 'guest'
            ? walkInForm.guestPhone.trim() || null
            : null
      }
    );
    emit('created', booking);
    isOpen.value = false;
    toast.add({ title: 'Walk-in booking created', color: 'success' });
  } catch (err: unknown) {
    const msg =
      (err as { data?: { message?: string } })?.data?.message ??
      'Failed to create walk-in booking.';
    if (msg.includes('already booked')) {
      walkInErrors.date = msg;
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
    :ui="{ width: 'sm:max-w-xl' }"
  >
    <template #body>
      <div class="space-y-4">
        <!-- Court -->
        <div>
          <label class="mb-1 block text-sm font-medium text-[#0f1728]">Court</label>
          <USelect
            v-model="walkInForm.courtId"
            :items="walkInCourtOptions"
            class="w-full"
          />
          <p v-if="walkInErrors.court" class="mt-1 text-red-600">
            {{ walkInErrors.court }}
          </p>
        </div>

        <!-- Sport -->
        <div>
          <label class="mb-1 block text-sm font-medium text-[#0f1728]">Sport</label>
          <USelect
            v-model="walkInForm.sport"
            :items="walkInSportOptions"
            :disabled="!walkInForm.courtId"
            class="w-full"
          />
          <p v-if="walkInErrors.sport" class="mt-1 text-red-600">
            {{ walkInErrors.sport }}
          </p>
        </div>

        <!-- Date + time -->
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-1">
            <label class="mb-1 block text-sm font-medium text-[#0f1728]">Date</label>
            <UInput
              v-model="walkInForm.date"
              type="date"
              :min="todayStr"
              class="w-full"
            />
            <p v-if="walkInErrors.date" class="mt-1 text-red-600">
              {{ walkInErrors.date }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-[#0f1728]">Start</label>
            <USelect
              v-model="walkInForm.startHour"
              :items="startTimeHourOptions"
              class="w-full"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-[#0f1728]">Duration</label>
            <USelect
              v-model="walkInForm.duration"
              :items="durationOptions"
              class="w-full"
            />
          </div>
        </div>

        <!-- Customer -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#0f1728]">Customer</label>
          <div class="mb-2 flex gap-2">
            <UButton
              size="sm"
              :variant="walkInForm.customerMode === 'guest' ? 'solid' : 'outline'"
              :color="walkInForm.customerMode === 'guest' ? 'primary' : 'neutral'"
              @click="walkInForm.customerMode = 'guest'"
            >
              Guest
            </UButton>
            <UButton
              size="sm"
              :variant="walkInForm.customerMode === 'registered' ? 'solid' : 'outline'"
              :color="walkInForm.customerMode === 'registered' ? 'primary' : 'neutral'"
              @click="walkInForm.customerMode = 'registered'"
            >
              Registered User
            </UButton>
          </div>

          <!-- Guest fields -->
          <template v-if="walkInForm.customerMode === 'guest'">
            <UInput
              v-model="walkInForm.guestName"
              placeholder="Full name"
              class="w-full"
            />
            <UInput
              v-model="walkInForm.guestPhone"
              placeholder="Phone number (optional)"
              class="mt-2 w-full"
            />
          </template>

          <!-- Registered user search -->
          <template v-else>
            <div
              v-if="selectedUser"
              class="flex items-center justify-between rounded-xl border border-[#dbe4ef] bg-[#f8fafc] px-3 py-2"
            >
              <div>
                <p class="text-sm font-medium text-[#0f1728]">
                  {{ selectedUser.name }}
                </p>
                <p class="text-[#64748b]">{{ selectedUser.email }}</p>
              </div>
              <UButton
                icon="i-heroicons-x-mark"
                color="neutral"
                variant="ghost"
                @click="clearSelectedUser"
              />
            </div>
            <div v-else class="relative">
              <UInput
                v-model="userSearchQuery"
                placeholder="Search by name, email, or phone…"
                :loading="userSearchLoading"
                class="w-full"
                icon="i-heroicons-magnifying-glass"
              />
              <!-- Results dropdown -->
              <div
                v-if="userSearchResults.length"
                class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-[#dbe4ef] bg-white shadow-lg"
              >
                <button
                  v-for="u in userSearchResults"
                  :key="u.id"
                  class="w-full px-3 py-2 text-left text-sm hover:bg-[#f0f4f8] transition-colors"
                  type="button"
                  @click="selectUser(u)"
                >
                  <span class="font-medium text-[#0f1728]">{{ u.name }}</span>
                  <span class="ml-2 text-[#64748b]">{{ u.email }}</span>
                </button>
              </div>
            </div>
          </template>

          <p v-if="walkInErrors.customer" class="mt-1 text-red-600">
            {{ walkInErrors.customer }}
          </p>
        </div>
      </div>
    </template>
    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton
          color="neutral"
          variant="outline"
          @click="isOpen = false"
        >
          Cancel
        </UButton>
        <UButton
          class="bg-[#004e89] hover:bg-[#003d6b]"
          :loading="walkInLoading"
          @click="submitWalkIn"
        >
          Confirm Walk-in
        </UButton>
      </div>
    </template>
  </UModal>
</template>
