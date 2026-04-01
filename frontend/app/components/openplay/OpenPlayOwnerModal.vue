<script setup lang="ts">
import { z } from 'zod';
import type { FormSubmitEvent } from '#ui/types';
import type { Court, OperatingHoursEntry } from '~/types/hub';
import type { OpenPlayParticipant, OpenPlaySession } from '~/types/openPlay';
import { useOwnerOpenPlay } from '~/composables/useOwnerOpenPlay';

const props = defineProps<{
  open: boolean;
  hubId: string;
  sessionId: string | null;
  courts: Court[];
  operatingHours?: OperatingHoursEntry[];
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  updated: [OpenPlaySession];
}>();

const {
  fetchSession,
  updateSession,
  fetchParticipants,
  confirmParticipant,
  rejectParticipant,
  cancelParticipant,
  cancelSession
} = useOwnerOpenPlay();
const toast = useToast();
const formRef = useTemplateRef('formRef');

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

const session = ref<OpenPlaySession | null>(null);
const sessionLoading = ref(false);
const participants = ref<OpenPlayParticipant[]>([]);
const participantsLoading = ref(false);
const saving = ref(false);

const schema = z
  .object({
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
    notes: z
      .string()
      .max(500, 'Max 500 characters.')
      .optional()
      .or(z.literal('')),
    guestsCanJoin: z.boolean()
  })
  .superRefine((data, ctx) => {
    if (data.endHour <= data.startHour) {
      ctx.addIssue({
        code: 'custom',
        message: 'End time must be after start time.',
        path: ['endHour']
      });
    }
  });

type Schema = z.infer<typeof schema>;

const state = reactive<Schema>({
  courtId: '',
  date: '',
  startHour: 8,
  endHour: 9,
  maxPlayers: 2,
  pricePerPlayer: 0,
  notes: '',
  guestsCanJoin: false
});

const courtOptions = computed(() =>
  props.courts.map((court) => ({ label: court.name, value: court.id }))
);

function formatHourLabel(hour: number): string {
  if (hour === 12) return '12:00 PM';
  if (hour < 12) return `${hour}:00 AM`;
  return `${hour - 12}:00 PM`;
}

function getOperatingRange(
  date: string
): { openHour: number; closeHour: number } | null {
  const operatingHours = props.operatingHours ?? [];
  if (!operatingHours.length || !date) return null;

  const [year, month, day] = date.split('-').map(Number);
  const dayOfWeek = new Date(year!, month! - 1, day!).getDay();
  const entry = operatingHours.find((item) => item.day_of_week === dayOfWeek);
  if (!entry || entry.is_closed) return null;

  return {
    openHour: parseInt(entry.opens_at.split(':')[0]!, 10),
    closeHour: parseInt(entry.closes_at.split(':')[0]!, 10)
  };
}

const startTimeHourOptions = computed(() => {
  const range = getOperatingRange(state.date);
  const openHour = range?.openHour ?? 6;
  const closeHour = range?.closeHour ?? 23;

  return Array.from({ length: closeHour - openHour }, (_, index) => {
    const hour = openHour + index;
    return { label: formatHourLabel(hour), value: hour };
  });
});

const endHourOptions = computed(() => {
  const range = getOperatingRange(state.date);
  const closeHour = range?.closeHour ?? 23;
  const minHour = state.startHour + 1;
  const count = closeHour - minHour + 1;
  if (count <= 0) return [];

  return Array.from({ length: count }, (_, index) => {
    const hour = minHour + index;
    return { label: formatHourLabel(hour), value: hour };
  });
});

watch(
  () => state.startHour,
  () => {
    if (state.endHour <= state.startHour) {
      state.endHour = state.startHour + 1;
    }
  }
);

const dateObj = computed({
  get() {
    if (!state.date) return new Date();
    const [year, month, day] = state.date.split('-').map(Number);
    return new Date(year!, month! - 1, day!);
  },
  set(value: Date) {
    state.date = `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
  }
});

function hydrateForm(nextSession: OpenPlaySession) {
  session.value = nextSession;

  const booking = nextSession.booking;
  if (!booking) return;

  const start = new Date(booking.start_time);
  const end = new Date(booking.end_time);
  state.courtId = booking.court_id;
  state.date = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`;
  state.startHour = start.getHours();
  state.endHour = end.getHours();
  state.maxPlayers = nextSession.max_players;
  state.pricePerPlayer = Number(nextSession.price_per_player);
  state.notes = nextSession.notes ?? '';
  state.guestsCanJoin = nextSession.guests_can_join;
}

async function loadSession() {
  if (!props.sessionId) return;

  sessionLoading.value = true;
  try {
    const nextSession = await fetchSession(props.hubId, props.sessionId);
    hydrateForm(nextSession);
  } catch {
    toast.add({ title: 'Failed to load open play session', color: 'error' });
    isOpen.value = false;
  } finally {
    sessionLoading.value = false;
  }
}

async function loadParticipants() {
  if (!props.sessionId) return;

  participantsLoading.value = true;
  try {
    participants.value = await fetchParticipants(props.hubId, props.sessionId);
  } catch {
    toast.add({ title: 'Failed to load participants', color: 'error' });
  } finally {
    participantsLoading.value = false;
  }
}

async function refreshAll() {
  await Promise.all([loadSession(), loadParticipants()]);
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
let hubChannel: any = null;

watch(
  [() => props.open, () => props.sessionId],
  async ([open, sessionId]) => {
    if (!open || !sessionId) {
      if (hubChannel) {
        const { $echo } = useNuxtApp();
        if ($echo) {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          ($echo as any).leaveChannel(`hub.${props.hubId}`);
        }
        hubChannel = null;
      }
      return;
    }

    await refreshAll();

    if (!hubChannel) {
      const { $echo } = useNuxtApp();
      if ($echo) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const echo = $echo as any;
        echo.connector.pusher.connection.connect();
        hubChannel = echo.channel(`hub.${props.hubId}`);
        hubChannel.listen('.booking.slot.updated', () => {
          loadParticipants();
        });
      }
    }
  },
  { immediate: true }
);

async function onSubmit(event: FormSubmitEvent<Schema>) {
  if (!props.sessionId) return;

  const {
    date,
    startHour,
    endHour,
    courtId,
    maxPlayers,
    pricePerPlayer,
    notes,
    guestsCanJoin
  } = event.data;

  const [year, month, day] = date.split('-').map(Number);
  const startDt = new Date(year!, month! - 1, day!);
  startDt.setHours(startHour, 0, 0, 0);
  const endDt = new Date(year!, month! - 1, day!);
  endDt.setHours(endHour, 0, 0, 0);

  saving.value = true;
  try {
    const updated = await updateSession(props.hubId, props.sessionId, {
      court_id: courtId,
      start_time: startDt.toISOString(),
      end_time: endDt.toISOString(),
      max_players: maxPlayers,
      price_per_player: pricePerPlayer,
      notes: notes?.trim() || null,
      guests_can_join: guestsCanJoin
    });

    hydrateForm(updated);
    toast.add({ title: 'Open play session updated', color: 'success' });
    emit('updated', updated);
  } catch (err: any) {
    const message =
      err?.data?.message ||
      err?.message ||
      'Failed to update open play session.';
    toast.add({
      title: 'Failed to update open play session',
      description: message,
      color: 'error'
    });
  } finally {
    saving.value = false;
  }
}

const confirmingId = ref<string | null>(null);

async function handleConfirm(participant: OpenPlayParticipant) {
  if (!props.sessionId) return;
  confirmingId.value = participant.id;
  try {
    await confirmParticipant(props.hubId, props.sessionId, participant.id);
    toast.add({ title: 'Payment confirmed', color: 'success' });
    await refreshAll();
    if (session.value) emit('updated', session.value);
  } catch {
    toast.add({ title: 'Failed to confirm payment', color: 'error' });
  } finally {
    confirmingId.value = null;
  }
}

const isRejectOpen = ref(false);
const rejectTarget = ref<OpenPlayParticipant | null>(null);
const rejectNote = ref('');
const rejectError = ref('');
const rejectingId = ref<string | null>(null);

function openReject(participant: OpenPlayParticipant) {
  rejectTarget.value = participant;
  rejectNote.value = '';
  rejectError.value = '';
  isRejectOpen.value = true;
}

async function submitReject() {
  if (!props.sessionId || !rejectTarget.value) return;
  if (!rejectNote.value.trim()) {
    rejectError.value = 'Please provide a rejection reason.';
    return;
  }

  rejectingId.value = rejectTarget.value.id;
  try {
    await rejectParticipant(
      props.hubId,
      props.sessionId,
      rejectTarget.value.id,
      rejectNote.value.trim()
    );
    isRejectOpen.value = false;
    toast.add({
      title: 'Receipt rejected. Participant can re-upload.',
      color: 'warning'
    });
    await refreshAll();
    if (session.value) emit('updated', session.value);
  } catch {
    toast.add({ title: 'Failed to reject receipt', color: 'error' });
  } finally {
    rejectingId.value = null;
  }
}

const isCancelOpen = ref(false);
const cancelTarget = ref<OpenPlayParticipant | null>(null);
const cancellingId = ref<string | null>(null);

function openCancelParticipant(participant: OpenPlayParticipant) {
  cancelTarget.value = participant;
  isCancelOpen.value = true;
}

async function submitCancelParticipant() {
  if (!props.sessionId || !cancelTarget.value) return;

  cancellingId.value = cancelTarget.value.id;
  try {
    await cancelParticipant(props.hubId, props.sessionId, cancelTarget.value.id);
    isCancelOpen.value = false;
    toast.add({ title: 'Participant cancelled', color: 'success' });
    await refreshAll();
    if (session.value) emit('updated', session.value);
  } catch {
    toast.add({ title: 'Failed to cancel participant', color: 'error' });
  } finally {
    cancellingId.value = null;
  }
}

const isCancelSessionOpen = ref(false);
const cancellingSession = ref(false);

async function submitCancelSession() {
  if (!props.sessionId) return;

  cancellingSession.value = true;
  try {
    await cancelSession(props.hubId, props.sessionId);
    isCancelSessionOpen.value = false;
    isOpen.value = false;
    toast.add({ title: 'Open play session cancelled', color: 'success' });
    if (session.value) emit('updated', session.value);
  } catch {
    toast.add({ title: 'Failed to cancel open play session', color: 'error' });
  } finally {
    cancellingSession.value = false;
  }
}

function participantName(participant: OpenPlayParticipant): string {
  if (participant.user) {
    return `${participant.user.first_name} ${participant.user.last_name}`.trim();
  }

  return participant.guest_name ?? 'Unknown';
}

function participantDropdownItems(participant: OpenPlayParticipant) {
  const groups: {
    label: string;
    icon: string;
    color?: 'error';
    loading?: boolean;
    onSelect: () => void;
  }[][] = [];

  if (
    participant.payment_status === 'payment_sent' ||
    participant.payment_status === 'pending_payment'
  ) {
    groups.push([
      {
        label:
          participant.payment_status === 'payment_sent'
            ? 'Confirm Payment'
            : 'Confirm Booking',
        icon: 'i-heroicons-check-circle',
        loading: confirmingId.value === participant.id,
        onSelect: () => handleConfirm(participant)
      },
      {
        label:
          participant.payment_status === 'payment_sent'
            ? 'Reject Receipt'
            : 'Reject',
        icon: 'i-heroicons-x-circle',
        color: 'error' as const,
        onSelect: () => openReject(participant)
      }
    ]);
  }

  if (participant.payment_status !== 'cancelled') {
    groups.push([
      {
        label: 'Cancel Participant',
        icon: 'i-heroicons-x-mark',
        color: 'error' as const,
        loading: cancellingId.value === participant.id,
        onSelect: () => openCancelParticipant(participant)
      }
    ]);
  }

  return groups;
}

const confirmedCount = computed(
  () => participants.value.filter((participant) => participant.payment_status === 'confirmed').length
);

const activeParticipantsCount = computed(() => participants.value.length);

function formatSessionTime(nextSession: OpenPlaySession | null): string {
  if (!nextSession?.booking) return '';
  const start = new Date(nextSession.booking.start_time);
  const end = new Date(nextSession.booking.end_time);
  const date = start.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  const startLabel = start.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  const endLabel = end.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });

  return `${date} · ${startLabel} – ${endLabel}`;
}
</script>

<template>
  <AppModal
    v-model:open="isOpen"
    title="Open Play Session"
    confirm="Save Changes"
    :confirm-loading="saving"
    :confirm-disabled="sessionLoading || saving || !session"
    :ui="{ content: 'sm:max-w-4xl' }"
    @confirm="formRef?.submit()"
  >
    <template #body>
      <div v-if="sessionLoading" class="py-10 text-center">
        <UIcon
          name="i-heroicons-arrow-path"
          class="h-6 w-6 animate-spin text-[#64748b]"
        />
      </div>

      <div v-else-if="session" class="space-y-6">
        <UForm
          ref="formRef"
          :schema="schema"
          :state="state"
          class="space-y-4"
          @submit="onSubmit"
        >
          <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            <div class="space-y-4">
              <div
                class="rounded-xl border border-[#dbe4ef] bg-[#f8fafc] p-4 text-sm"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p class="font-semibold text-[#0f1728]">
                      {{ session.booking?.court?.name ?? 'Court' }}
                    </p>
                    <p class="mt-1 text-[#64748b]">
                      {{ formatSessionTime(session) }}
                    </p>
                  </div>
                  <UBadge
                    :label="`${activeParticipantsCount} / ${state.maxPlayers} active · ${confirmedCount} confirmed`"
                    color="success"
                    variant="subtle"
                  />
                </div>
                <p class="mt-2 text-[#64748b]">
                  ₱{{ Number(state.pricePerPlayer).toLocaleString('en-PH', { maximumFractionDigits: 2 }) }}
                  / player
                  <span v-if="state.notes"> · {{ state.notes }}</span>
                </p>
              </div>

              <UFormField label="Court" name="courtId">
                <USelect
                  v-model="state.courtId"
                  :items="courtOptions"
                  class="w-full"
                />
              </UFormField>

              <div class="grid grid-cols-3 gap-3">
                <UFormField label="Date" name="date" class="col-span-1">
                  <AppDatePicker
                    v-model="dateObj"
                    variant="nav"
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
                    v-model="state.startHour"
                    :items="startTimeHourOptions"
                    class="w-full"
                  />
                </UFormField>
                <UFormField label="End" name="endHour">
                  <USelect
                    v-model="state.endHour"
                    :items="endHourOptions"
                    class="w-full"
                  />
                </UFormField>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <UFormField label="Max Players" name="maxPlayers">
                  <UInput
                    v-model.number="state.maxPlayers"
                    type="number"
                    :min="2"
                    class="w-full"
                  />
                </UFormField>
                <UFormField label="Price per Player (₱)" name="pricePerPlayer">
                  <UInput
                    v-model.number="state.pricePerPlayer"
                    type="number"
                    :min="0"
                    step="0.01"
                    class="w-full"
                  />
                </UFormField>
              </div>

              <UFormField label="Notes (optional)" name="notes">
                <UTextarea
                  v-model="state.notes"
                  :rows="3"
                  :maxlength="500"
                  placeholder="e.g. Bring your own racket"
                  class="w-full"
                />
              </UFormField>

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
                <USwitch v-model="state.guestsCanJoin" />
              </div>
            </div>

            <div class="space-y-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h3 class="text-sm font-semibold text-[#0f1728]">
                    Participants
                  </h3>
                  <p class="text-sm text-[#64748b]">
                    Manage receipts, confirmations, and removals.
                  </p>
                </div>
                <UButton
                  v-if="session.status !== 'cancelled'"
                  size="sm"
                  color="error"
                  variant="outline"
                  icon="i-heroicons-x-mark"
                  @click="isCancelSessionOpen = true"
                >
                  Cancel Session
                </UButton>
              </div>

              <div v-if="participantsLoading" class="py-8 text-center">
                <UIcon
                  name="i-heroicons-arrow-path"
                  class="h-6 w-6 animate-spin text-[#64748b]"
                />
              </div>

              <div
                v-else-if="!participants.length"
                class="rounded-xl border border-[#dbe4ef] bg-white py-8 text-center text-sm text-[#64748b]"
              >
                No participants yet.
              </div>

              <div
                v-else
                class="overflow-x-auto rounded-xl border border-[#dbe4ef]"
              >
                <table class="w-full text-sm">
                  <thead
                    class="bg-[#f8fafc] text-xs font-medium uppercase text-[#64748b]"
                  >
                    <tr>
                      <th class="px-4 py-2.5 text-left">Name</th>
                      <th class="px-4 py-2.5 text-left">Type</th>
                      <th class="px-4 py-2.5 text-left">Payment</th>
                      <th class="px-4 py-2.5" />
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#f1f5f9]">
                    <tr
                      v-for="participant in participants"
                      :key="participant.id"
                      class="bg-white hover:bg-[#f8fafc]"
                    >
                      <td class="px-4 py-3 font-medium text-[#0f1728]">
                        {{ participantName(participant) }}
                        <span
                          v-if="participant.guest_email"
                          class="block text-xs font-normal text-[#64748b]"
                        >
                          {{ participant.guest_email }}
                        </span>
                      </td>
                      <td class="px-4 py-3">
                        <UBadge
                          :label="participant.user_id ? 'Registered' : 'Guest'"
                          :color="participant.user_id ? 'primary' : 'neutral'"
                          variant="subtle"
                        />
                      </td>
                      <td class="px-4 py-3 text-[#64748b]">
                        {{
                          participant.payment_method === 'pay_on_site'
                            ? 'Pay on Site'
                            : 'Digital Bank'
                        }}
                      </td>
                      <td class="px-4 py-3">
                        <UDropdownMenu
                          v-if="participantDropdownItems(participant).length"
                          :items="participantDropdownItems(participant)"
                        >
                          <UButton
                            icon="i-heroicons-ellipsis-horizontal"
                            color="neutral"
                            variant="ghost"
                          />
                        </UDropdownMenu>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <button type="submit" class="hidden" />
        </UForm>
      </div>
    </template>
  </AppModal>

  <AppModal
    v-model:open="isRejectOpen"
    title="Reject Receipt"
    cancel-variant="outline"
    confirm="Reject Receipt"
    confirm-color="error"
    :confirm-loading="rejectingId !== null"
    @confirm="submitReject"
  >
    <template #body>
      <p class="mb-3 text-sm text-[#64748b]">
        Provide a reason so the participant knows what to re-upload.
      </p>
      <UTextarea
        v-model="rejectNote"
        placeholder="e.g. Receipt is blurry, wrong amount shown…"
        :rows="4"
        :maxlength="500"
        class="w-full"
      />
      <p v-if="rejectError" class="mt-1.5 text-red-600">{{ rejectError }}</p>
    </template>
  </AppModal>

  <AppModal
    v-model:open="isCancelOpen"
    title="Cancel Participant"
    cancel="Keep Participant"
    cancel-variant="outline"
    confirm="Yes, Cancel"
    confirm-color="error"
    :confirm-loading="cancellingId !== null"
    @confirm="submitCancelParticipant"
  >
    <template #body>
      <p class="text-sm text-[#64748b]">
        Are you sure you want to remove
        <span class="font-medium text-[#0f1728]">{{
          cancelTarget ? participantName(cancelTarget) : ''
        }}</span>
        from this session?
      </p>
    </template>
  </AppModal>

  <AppModal
    v-model:open="isCancelSessionOpen"
    title="Cancel Open Play Session"
    cancel="Keep Session"
    cancel-variant="outline"
    confirm="Yes, Cancel Session"
    confirm-color="error"
    :confirm-loading="cancellingSession"
    @confirm="submitCancelSession"
  >
    <template #body>
      <p class="text-sm text-[#64748b]">
        This will cancel the entire session and notify all active participants.
        This cannot be undone.
      </p>
    </template>
  </AppModal>
</template>
