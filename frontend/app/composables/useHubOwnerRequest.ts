import type { HubOwnerRequestStatus } from '~/types/user';

export interface HubOwnerRequest {
  id: string;
  status: Exclude<HubOwnerRequestStatus, 'none'>;
  hub_name: string | null;
  city: string | null;
  contact_number: string | null;
  message: string;
  review_notes: string | null;
  reviewed_at: string | null;
  created_at: string;
}

interface HubOwnerRequestResponse {
  data: HubOwnerRequest | null;
}

interface CreateHubOwnerRequestResponse extends HubOwnerRequestResponse {
  message: string;
}

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

interface PanelHubOwnerRequestActionResponse {
  message: string;
  data: PanelHubOwnerRequest;
}

export function useHubOwnerRequest() {
  const { apiFetch } = useApi();
  const authStore = useAuthStore();

  const currentRequest = useState<HubOwnerRequest | null>(
    'hub-owner-request.current',
    () => null
  );
  const currentRequestLoaded = useState<boolean>(
    'hub-owner-request.loaded',
    () => false
  );

  const isAuthenticated = computed(() => authStore.isAuthenticated);
  const currentStatus = computed<HubOwnerRequestStatus>(() => {
    if (!authStore.user) return 'none';
    return authStore.user.hub_owner_request_status ?? 'none';
  });

  const canApply = computed(() => authStore.user?.role === 'user');
  const hasPendingRequest = computed(() => currentStatus.value === 'pending');
  const applyCtaLabel = computed(() =>
    hasPendingRequest.value ? 'Request Pending' : 'Apply as Hub Owner'
  );
  const applyRoute = computed(() =>
    isAuthenticated.value
      ? '/apply-hub-owner'
      : '/auth/login?redirect=/apply-hub-owner'
  );

  async function fetchCurrentRequest(force = false): Promise<HubOwnerRequest | null> {
    if (!authStore.token) {
      currentRequest.value = null;
      currentRequestLoaded.value = true;
      return null;
    }

    if (currentRequestLoaded.value && !force) {
      return currentRequest.value;
    }

    const response = await apiFetch<HubOwnerRequestResponse>('/hub-owner-request');
    currentRequest.value = response.data;
    currentRequestLoaded.value = true;
    return response.data;
  }

  async function submitApplication(payload: {
    hub_name?: string | null;
    city?: string | null;
    contact_number?: string | null;
    message: string;
  }): Promise<CreateHubOwnerRequestResponse> {
    const response = await apiFetch<CreateHubOwnerRequestResponse>(
      '/hub-owner-request',
      {
        method: 'POST',
        body: payload
      }
    );

    currentRequest.value = response.data;
    currentRequestLoaded.value = true;

    if (authStore.user) {
      authStore.user.hub_owner_request_status = response.data?.status ?? 'pending';
    }

    return response;
  }

  async function approveRequest(id: string): Promise<PanelHubOwnerRequestActionResponse> {
    return await apiFetch<PanelHubOwnerRequestActionResponse>(
      `/panel/hub-owner-requests/${id}/approve`,
      { method: 'POST' }
    );
  }

  async function rejectRequest(id: string, review_notes?: string): Promise<PanelHubOwnerRequestActionResponse> {
    return await apiFetch<PanelHubOwnerRequestActionResponse>(
      `/panel/hub-owner-requests/${id}/reject`,
      {
        method: 'POST',
        body: { review_notes }
      }
    );
  }

  return {
    currentRequest,
    currentStatus,
    canApply,
    hasPendingRequest,
    applyCtaLabel,
    applyRoute,
    fetchCurrentRequest,
    submitApplication,
    approveRequest,
    rejectRequest
  };
}
