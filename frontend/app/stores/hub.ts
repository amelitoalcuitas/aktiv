import type { Hub } from '~/types/hub';
import { useApi } from '~/utils/api';

interface HubListResponse {
  data: Hub[];
}

export const useHubStore = defineStore('hub', () => {
  const myHubs = ref<Hub[]>([]);
  const loading = ref(false);
  const initialized = ref(false);

  async function fetchMyHubs() {
    loading.value = true;
    try {
      const { apiFetch } = useApi();
      const res = await apiFetch<HubListResponse>('/dashboard/hubs');
      myHubs.value = res.data;
    } finally {
      loading.value = false;
      initialized.value = true;
    }
  }

  return { myHubs, loading, initialized, fetchMyHubs };
});
