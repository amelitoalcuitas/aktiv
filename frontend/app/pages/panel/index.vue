<script setup lang="ts">
definePageMeta({ layout: 'panel', middleware: ['auth', 'superadmin'] });

useHead({ title: 'Admin Panel · Aktiv' });

const { apiFetch } = useApi();

interface PanelStats {
  total_hubs: number;
  active_hubs: number;
  total_users: number;
}

const loading = ref(true);
const stats = ref<PanelStats | null>(null);

onMounted(async () => {
  try {
    stats.value = await apiFetch<PanelStats>('/panel/stats');
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-[#0f1728]">Overview</h1>
      <p class="mt-1 text-sm text-[#64748b]">Platform-wide summary.</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center gap-2 text-[#64748b]">
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading…</span>
    </div>

    <div v-else-if="stats" class="grid gap-4 sm:grid-cols-3">
      <!-- Total Hubs -->
      <NuxtLink
        to="/panel/hubs"
        class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5 transition hover:shadow-md"
      >
        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#e8f0f8]">
          <UIcon name="i-heroicons-building-office-2" class="h-6 w-6 text-[#004e89]" />
        </div>
        <div>
          <p class="text-sm text-[#64748b]">Total Hubs</p>
          <p class="text-2xl font-bold text-[#0f1728]">{{ stats.total_hubs }}</p>
        </div>
      </NuxtLink>

      <!-- Active Hubs -->
      <div class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5">
        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-green-50">
          <UIcon name="i-heroicons-check-circle" class="h-6 w-6 text-green-600" />
        </div>
        <div>
          <p class="text-sm text-[#64748b]">Active Hubs</p>
          <p class="text-2xl font-bold text-[#0f1728]">{{ stats.active_hubs }}</p>
        </div>
      </div>

      <!-- Total Users -->
      <NuxtLink
        to="/panel/users"
        class="flex items-center gap-4 rounded-2xl border border-[#dbe4ef] bg-white p-5 transition hover:shadow-md"
      >
        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-purple-50">
          <UIcon name="i-heroicons-users" class="h-6 w-6 text-purple-600" />
        </div>
        <div>
          <p class="text-sm text-[#64748b]">Total Users</p>
          <p class="text-2xl font-bold text-[#0f1728]">{{ stats.total_users }}</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
