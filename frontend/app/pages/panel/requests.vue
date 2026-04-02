<script setup lang="ts">
import type { HubOwnerRequest } from '~/composables/useHubOwnerRequest';

definePageMeta({ layout: 'panel', middleware: ['auth', 'superadmin'] });

useHead({ title: 'Hub Owner Requests · Super Admin Panel · Aktiv' });

interface PanelHubOwnerRequest extends HubOwnerRequest {
  user: {
    id: string;
    name: string;
    email: string;
  };
  reviewer: {
    id: string;
    name: string;
    email: string;
  } | null;
}

interface PaginatedRequests {
  data: PanelHubOwnerRequest[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

const toast = useToast();
const { apiFetch } = useApi();
const { approveRequest, rejectRequest } = useHubOwnerRequest();

const activeStatus = ref<'pending' | 'approved' | 'rejected'>('pending');
const page = ref(1);
const loading = ref(false);
const result = ref<PaginatedRequests | null>(null);

const statusOptions = [
  { label: 'Pending', value: 'pending' as const },
  { label: 'Approved', value: 'approved' as const },
  { label: 'Rejected', value: 'rejected' as const }
];

const rejectReasonItems = [
  {
    label: 'Incomplete hub details',
    value: 'Please add more complete information about the hub, including its name, location, and what facilities you manage.'
  },
  {
    label: 'Insufficient ownership context',
    value: 'Please provide more context about your connection to the venue and your role in managing the hub.'
  },
  {
    label: 'Missing contact verification details',
    value: 'Please share a valid contact number and any other details we can use to verify your application.'
  },
  {
    label: 'Application message too brief',
    value: 'Please add more detail about the venue, the sports offered, and how you plan to use Aktiv as a hub owner.'
  },
  {
    label: 'Hub is not ready for onboarding',
    value: 'Please reapply once the hub is ready for onboarding with clearer operational details and availability.'
  },
  {
    label: 'Custom reason',
    value: '__custom__'
  }
];

const approveModal = ref({
  open: false,
  item: null as PanelHubOwnerRequest | null,
  loading: false
});

const rejectModal = ref({
  open: false,
  item: null as PanelHubOwnerRequest | null,
  loading: false,
  selectedReason: '',
  notes: ''
});

async function fetchRequests() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      status: activeStatus.value
    });

    result.value = await apiFetch<PaginatedRequests>(
      `/panel/hub-owner-requests?${params.toString()}`
    );
  } catch {
    toast.add({ title: 'Failed to load requests.', color: 'error' });
  } finally {
    loading.value = false;
  }
}

function formatDateTime(iso: string | null) {
  if (!iso) return '—';

  return formatInViewerTimezone(iso, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
}

function openApproveModal(item: PanelHubOwnerRequest) {
  approveModal.value = {
    open: true,
    item,
    loading: false
  };
}

function openRejectModal(item: PanelHubOwnerRequest) {
  rejectModal.value = {
    open: true,
    item,
    loading: false,
    selectedReason: '',
    notes: ''
  };
}

function handleRejectReasonChange(value: string) {
  rejectModal.value.selectedReason = value;
  rejectModal.value.notes = value === '__custom__' ? '' : value;
}

async function submitApprove() {
  if (!approveModal.value.item) return;

  approveModal.value.loading = true;
  try {
    await approveRequest(approveModal.value.item.id);
    approveModal.value.open = false;
    toast.add({
      title: 'Request approved',
      description: 'The applicant now has dashboard access.',
      color: 'success'
    });
    await fetchRequests();
  } catch (error: any) {
    toast.add({
      title: error?.data?.message ?? 'Failed to approve request.',
      color: 'error'
    });
  } finally {
    approveModal.value.loading = false;
  }
}

async function submitReject() {
  if (!rejectModal.value.item) return;

  rejectModal.value.loading = true;
  try {
    await rejectRequest(rejectModal.value.item.id, rejectModal.value.notes.trim() || undefined);
    rejectModal.value.open = false;
    toast.add({
      title: 'Request rejected',
      description: 'The applicant has been notified by email.',
      color: 'success'
    });
    await fetchRequests();
  } catch (error: any) {
    toast.add({
      title: error?.data?.message ?? 'Failed to reject request.',
      color: 'error'
    });
  } finally {
    rejectModal.value.loading = false;
  }
}

watch(activeStatus, () => {
  page.value = 1;
  fetchRequests();
});

watch(page, fetchRequests);

onMounted(fetchRequests);
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#0f1728]">Hub Owner Requests</h1>
        <p class="mt-1 text-sm text-[#64748b]">
          <template v-if="result">{{ result.total }} {{ activeStatus }} request(s)</template>
          <template v-else>Review incoming owner access applications.</template>
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <UButton
          v-for="option in statusOptions"
          :key="option.value"
          :variant="activeStatus === option.value ? 'solid' : 'outline'"
          :color="activeStatus === option.value ? 'primary' : 'neutral'"
          :class="
            activeStatus === option.value
              ? 'bg-[#004e89] hover:bg-[#003d6b]'
              : ''
          "
          @click="activeStatus = option.value"
        >
          {{ option.label }}
        </UButton>
      </div>
    </div>

    <div v-if="loading && !result" class="flex items-center gap-2 text-[#64748b]">
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading requests…</span>
    </div>

    <template v-else-if="result">
      <div
        v-if="!result.data.length"
        class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-12 text-center"
      >
        <UIcon
          name="i-heroicons-inbox-stack"
          class="mx-auto h-12 w-12 text-[#c8d5e0]"
        />
        <h3 class="mt-4 text-base font-semibold text-[#0f1728]">
          No {{ activeStatus }} requests
        </h3>
        <p class="mt-1 text-sm text-[#64748b]">
          New applications will show up here once users start applying.
        </p>
      </div>

      <div v-else class="space-y-4">
        <UCard
          v-for="item in result.data"
          :key="item.id"
          class="rounded-2xl border border-[#dbe4ef] bg-white"
          :ui="{ root: 'ring-0 divide-y-0' }"
        >
          <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-[#0f1728]">
                  {{ item.user.name }}
                </h2>
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    item.status === 'pending'
                      ? 'bg-amber-100 text-amber-800'
                      : item.status === 'approved'
                        ? 'bg-[#daf7d0] text-[#1e6a0f]'
                        : 'bg-[#fee2e2] text-[#9f1239]'
                  "
                >
                  {{ item.status }}
                </span>
              </div>

              <p class="mt-1 text-sm text-[#64748b]">{{ item.user.email }}</p>

              <div class="mt-4 grid gap-4 text-sm text-[#475569] md:grid-cols-2">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                    Submitted
                  </p>
                  <p class="mt-1">{{ formatDateTime(item.created_at) }}</p>
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                    Contact Number
                  </p>
                  <p class="mt-1">{{ item.contact_number || '—' }}</p>
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                    Hub Name
                  </p>
                  <p class="mt-1">{{ item.hub_name || '—' }}</p>
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                    City
                  </p>
                  <p class="mt-1">{{ item.city || '—' }}</p>
                </div>
              </div>

              <div class="mt-4 rounded-2xl bg-[#f8fafc] p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                  Application Message
                </p>
                <p class="mt-2 text-sm leading-7 text-[#334155]">
                  {{ item.message }}
                </p>
              </div>

              <div v-if="item.review_notes" class="mt-4 rounded-2xl bg-[#fff8f8] p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                  Review Note
                </p>
                <p class="mt-2 text-sm leading-7 text-[#7f1d1d]">
                  {{ item.review_notes }}
                </p>
              </div>
            </div>

            <div class="flex min-w-[220px] flex-col gap-3">
              <div class="rounded-2xl border border-[#e2e8f0] p-4 text-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#94a3b8]">
                  Review Info
                </p>
                <p class="mt-2 text-[#475569]">
                  Reviewed at: <strong class="text-[#0f1728]">{{ formatDateTime(item.reviewed_at) }}</strong>
                </p>
                <p class="mt-1 text-[#475569]">
                  Reviewer:
                  <strong class="text-[#0f1728]">
                    {{ item.reviewer?.name || '—' }}
                  </strong>
                </p>
              </div>

              <template v-if="item.status === 'pending'">
                <UButton
                  class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
                  @click="openApproveModal(item)"
                >
                  Approve Request
                </UButton>
                <UButton
                  color="error"
                  variant="outline"
                  @click="openRejectModal(item)"
                >
                  Reject Request
                </UButton>
              </template>
            </div>
          </div>
        </UCard>
      </div>

      <div
        v-if="result.last_page > 1"
        class="mt-4 flex items-center justify-between text-sm text-[#64748b]"
      >
        <p>Page {{ result.current_page }} of {{ result.last_page }}</p>
        <div class="flex gap-2">
          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            :disabled="page === 1"
            icon="i-heroicons-chevron-left"
            @click="page--"
          >
            Previous
          </UButton>
          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            :disabled="page === result.last_page"
            trailing-icon="i-heroicons-chevron-right"
            @click="page++"
          >
            Next
          </UButton>
        </div>
      </div>
    </template>

    <AppModal
      v-model:open="approveModal.open"
      title="Approve Request"
      :ui="{ content: 'max-w-sm' }"
      confirm="Approve"
      confirm-color="primary"
      :confirm-loading="approveModal.loading"
      @confirm="submitApprove"
    >
      <template #body>
        <p class="text-sm leading-7 text-[#0f1728]">
          Approve hub owner access for
          <strong>{{ approveModal.item?.user.name }}</strong
          >? This will promote the user to owner and send their approval email.
        </p>
      </template>
    </AppModal>

    <AppModal
      v-model:open="rejectModal.open"
      title="Reject Request"
      :ui="{ content: 'max-w-md' }"
      confirm="Reject"
      confirm-color="error"
      :confirm-loading="rejectModal.loading"
      @confirm="submitReject"
    >
      <template #body>
        <div class="space-y-4">
          <p class="text-sm leading-7 text-[#0f1728]">
            Reject the application from
            <strong>{{ rejectModal.item?.user.name }}</strong
            >? An email will be sent to the applicant after this action.
          </p>

          <UFormField label="Review note (optional)">
            <USelect
              :model-value="rejectModal.selectedReason"
              :items="rejectReasonItems"
              value-key="value"
              label-key="label"
              class="mb-3 w-full"
              placeholder="Choose a premade reason"
              @update:model-value="handleRejectReasonChange"
            />
            <UTextarea
              v-model="rejectModal.notes"
              :rows="5"
              placeholder="Add context that should appear in the rejection email."
              class="w-full"
            />
          </UFormField>
        </div>
      </template>
    </AppModal>
  </div>
</template>
