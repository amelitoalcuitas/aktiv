<script setup lang="ts">
import type { OpenPlaySession, OpenPlayParticipant, ParticipantPaymentStatus } from '~/types/openPlay';
import { useOwnerOpenPlay } from '~/composables/useOwnerOpenPlay';

const props = defineProps<{
  open: boolean;
  hubId: string;
  session: OpenPlaySession;
}>();

const emit = defineEmits<{
  'update:open': [boolean];
  updated: [];
}>();

const { fetchParticipants, confirmParticipant, rejectParticipant, cancelParticipant, cancelSession } = useOwnerOpenPlay();
const toast = useToast();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

// ── Participant list ──────────────────────────────────────────
const participants = ref<OpenPlayParticipant[]>([]);
const loading = ref(false);

async function load() {
  loading.value = true;
  try {
    participants.value = await fetchParticipants(props.hubId, props.session.id);
  } catch {
    toast.add({ title: 'Failed to load participants', color: 'error' });
  } finally {
    loading.value = false;
  }
}

watch(
  () => props.open,
  (val) => {
    if (val) load();
  },
  { immediate: true }
);

// ── Confirm participant ───────────────────────────────────────
const confirmingId = ref<string | null>(null);

async function handleConfirm(p: OpenPlayParticipant) {
  confirmingId.value = p.id;
  try {
    const updated = await confirmParticipant(props.hubId, props.session.id, p.id);
    replaceParticipant(updated);
    toast.add({ title: 'Payment confirmed', color: 'success' });
    emit('updated');
  } catch {
    toast.add({ title: 'Failed to confirm payment', color: 'error' });
  } finally {
    confirmingId.value = null;
  }
}

// ── Reject receipt ────────────────────────────────────────────
const isRejectOpen = ref(false);
const rejectTarget = ref<OpenPlayParticipant | null>(null);
const rejectNote = ref('');
const rejectError = ref('');
const rejectingId = ref<string | null>(null);

function openReject(p: OpenPlayParticipant) {
  rejectTarget.value = p;
  rejectNote.value = '';
  rejectError.value = '';
  isRejectOpen.value = true;
}

async function submitReject() {
  if (!rejectTarget.value) return;
  if (!rejectNote.value.trim()) {
    rejectError.value = 'Please provide a rejection reason.';
    return;
  }
  rejectingId.value = rejectTarget.value.id;
  try {
    const updated = await rejectParticipant(
      props.hubId,
      props.session.id,
      rejectTarget.value.id,
      rejectNote.value.trim()
    );
    replaceParticipant(updated);
    isRejectOpen.value = false;
    toast.add({ title: 'Receipt rejected. Participant can re-upload.', color: 'warning' });
    emit('updated');
  } catch {
    toast.add({ title: 'Failed to reject receipt', color: 'error' });
  } finally {
    rejectingId.value = null;
  }
}

// ── Cancel participant ────────────────────────────────────────
const isCancelOpen = ref(false);
const cancelTarget = ref<OpenPlayParticipant | null>(null);
const cancellingId = ref<string | null>(null);

function openCancelParticipant(p: OpenPlayParticipant) {
  cancelTarget.value = p;
  isCancelOpen.value = true;
}

async function submitCancelParticipant() {
  if (!cancelTarget.value) return;
  cancellingId.value = cancelTarget.value.id;
  try {
    await cancelParticipant(props.hubId, props.session.id, cancelTarget.value.id);
    participants.value = participants.value.map((p) =>
      p.id === cancelTarget.value!.id
        ? { ...p, payment_status: 'cancelled', cancelled_by: 'owner' }
        : p
    );
    isCancelOpen.value = false;
    toast.add({ title: 'Participant cancelled', color: 'success' });
    emit('updated');
  } catch {
    toast.add({ title: 'Failed to cancel participant', color: 'error' });
  } finally {
    cancellingId.value = null;
  }
}

// ── Cancel session ────────────────────────────────────────────
const isCancelSessionOpen = ref(false);
const cancellingSession = ref(false);

async function submitCancelSession() {
  cancellingSession.value = true;
  try {
    await cancelSession(props.hubId, props.session.id);
    isCancelSessionOpen.value = false;
    isOpen.value = false;
    toast.add({ title: 'Open play session cancelled', color: 'success' });
    emit('updated');
  } catch {
    toast.add({ title: 'Failed to cancel session', color: 'error' });
  } finally {
    cancellingSession.value = false;
  }
}

// ── Helpers ───────────────────────────────────────────────────
function replaceParticipant(updated: OpenPlayParticipant) {
  const idx = participants.value.findIndex((p) => p.id === updated.id);
  if (idx >= 0) participants.value[idx] = updated;
}

function participantName(p: OpenPlayParticipant): string {
  if (p.user) return `${p.user.first_name} ${p.user.last_name}`.trim();
  return p.guest_name ?? 'Unknown';
}

function statusColor(
  status: ParticipantPaymentStatus
): 'warning' | 'primary' | 'success' | 'error' | 'neutral' {
  switch (status) {
    case 'pending_payment': return 'warning';
    case 'payment_sent': return 'primary';
    case 'confirmed': return 'success';
    case 'cancelled': return 'error';
  }
}

function statusLabel(status: ParticipantPaymentStatus): string {
  switch (status) {
    case 'pending_payment': return 'Pending';
    case 'payment_sent': return 'Receipt Sent';
    case 'confirmed': return 'Confirmed';
    case 'cancelled': return 'Cancelled';
  }
}

function participantDropdownItems(p: OpenPlayParticipant) {
  const groups: {
    label: string;
    icon: string;
    color?: 'error';
    loading?: boolean;
    onSelect: () => void;
  }[][] = [];

  if (p.payment_status === 'payment_sent' || p.payment_status === 'pending_payment') {
    groups.push([
      {
        label: p.payment_status === 'payment_sent' ? 'Confirm Payment' : 'Confirm Booking',
        icon: 'i-heroicons-check-circle',
        loading: confirmingId.value === p.id,
        onSelect: () => handleConfirm(p)
      },
      {
        label: p.payment_status === 'payment_sent' ? 'Reject Receipt' : 'Reject',
        icon: 'i-heroicons-x-circle',
        color: 'error' as const,
        onSelect: () => openReject(p)
      }
    ]);
  }

  if (p.payment_status !== 'cancelled') {
    groups.push([
      {
        label: 'Cancel Participant',
        icon: 'i-heroicons-x-mark',
        color: 'error' as const,
        loading: cancellingId.value === p.id,
        onSelect: () => openCancelParticipant(p)
      }
    ]);
  }

  return groups;
}

const confirmedCount = computed(
  () => participants.value.filter((p) => p.payment_status === 'confirmed').length
);

function formatSessionTime(session: OpenPlaySession): string {
  if (!session.booking) return '';
  const s = new Date(session.booking.start_time);
  const e = new Date(session.booking.end_time);
  const date = s.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  const start = s.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  const end = e.toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
  return `${date} · ${start} – ${end}`;
}
</script>

<template>
  <AppModal
    v-model:open="isOpen"
    title="Open Play"
    :ui="{ content: 'sm:max-w-2xl' }"
    :confirm="undefined"
    cancel="Close"
    cancel-variant="outline"
  >
    <template #body>
      <!-- Session summary -->
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-sm text-[#64748b]">
            {{ session.booking?.court?.name }} · {{ formatSessionTime(session) }}
          </p>
          <p class="mt-1 text-sm text-[#64748b]">
            ₱{{ session.price_per_player }} / player
            <span v-if="session.notes"> · {{ session.notes }}</span>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <UBadge
            :label="`${confirmedCount} / ${session.max_players} confirmed`"
            color="success"
            variant="subtle"
          />
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
      </div>

      <!-- Participants -->
      <div v-if="loading" class="py-8 text-center">
        <UIcon name="i-heroicons-arrow-path" class="h-6 w-6 animate-spin text-[#64748b]" />
      </div>

      <div
        v-else-if="!participants.length"
        class="py-8 text-center text-sm text-[#64748b]"
      >
        No participants yet.
      </div>

      <div v-else class="overflow-x-auto rounded-xl border border-[#dbe4ef]">
        <table class="w-full text-sm">
          <thead class="bg-[#f8fafc] text-xs font-medium uppercase text-[#64748b]">
            <tr>
              <th class="px-4 py-2.5 text-left">Name</th>
              <th class="px-4 py-2.5 text-left">Type</th>
              <th class="px-4 py-2.5 text-left">Payment</th>
              <th class="px-4 py-2.5 text-left">Status</th>
              <th class="px-4 py-2.5 text-left">Receipt</th>
              <th class="px-4 py-2.5" />
            </tr>
          </thead>
          <tbody class="divide-y divide-[#f1f5f9]">
            <tr v-for="p in participants" :key="p.id" class="bg-white hover:bg-[#f8fafc]">
              <td class="px-4 py-3 font-medium text-[#0f1728]">
                {{ participantName(p) }}
                <span v-if="p.guest_email" class="block text-xs font-normal text-[#64748b]">
                  {{ p.guest_email }}
                </span>
              </td>
              <td class="px-4 py-3">
                <UBadge
                  :label="p.user_id ? 'Registered' : 'Guest'"
                  :color="p.user_id ? 'primary' : 'neutral'"
                  variant="subtle"
                />
              </td>
              <td class="px-4 py-3 text-[#64748b]">
                {{ p.payment_method === 'pay_on_site' ? 'Pay on Site' : 'Digital Bank' }}
              </td>
              <td class="px-4 py-3">
                <div class="space-y-1">
                  <UBadge
                    :label="statusLabel(p.payment_status)"
                    :color="statusColor(p.payment_status)"
                    variant="subtle"
                  />
                  <p
                    v-if="p.payment_note && p.payment_status === 'pending_payment'"
                    class="rounded bg-[#fef9c3] px-1.5 py-0.5 text-xs text-[#92400e]"
                  >
                    {{ p.payment_note }}
                  </p>
                </div>
              </td>
              <td class="px-4 py-3">
                <a
                  v-if="p.receipt_image_url"
                  :href="p.receipt_image_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <img
                    :src="p.receipt_image_url"
                    alt="Receipt"
                    class="h-10 w-10 rounded-md border border-[#dbe4ef] object-cover transition-opacity hover:opacity-75"
                  />
                </a>
                <span v-else class="text-[#c8d5e0]">—</span>
              </td>
              <td class="px-4 py-3">
                <UDropdownMenu
                  v-if="participantDropdownItems(p).length"
                  :items="participantDropdownItems(p)"
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
    </template>
  </AppModal>

  <!-- Reject receipt modal -->
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

  <!-- Cancel participant confirmation -->
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
        <span class="font-medium text-[#0f1728]">{{ cancelTarget ? participantName(cancelTarget) : '' }}</span>
        from this session?
      </p>
    </template>
  </AppModal>

  <!-- Cancel session confirmation -->
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
