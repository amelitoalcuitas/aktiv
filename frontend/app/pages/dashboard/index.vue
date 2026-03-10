<script setup lang="ts">
import { useHubs } from '~/composables/useHubs';
import { useHubStore } from '~/stores/hub';
import type { Hub } from '~/types/hub';

definePageMeta({ layout: 'dashboard', middleware: 'auth' });

const hubStore = useHubStore();
const { deleteHub } = useHubs();
const toast = useToast();

// ── State ─────────────────────────────────────────────────────────────────────

const isDeleteOpen = ref(false);
const selectedHub = ref<Hub | null>(null);
const deleteLoading = ref(false);

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await hubStore.fetchMyHubs();
});

// ── Delete Hub ────────────────────────────────────────────────────────────────

function openDelete(hub: Hub) {
  selectedHub.value = hub;
  isDeleteOpen.value = true;
}

async function confirmDelete() {
  if (!selectedHub.value) return;
  deleteLoading.value = true;
  try {
    await deleteHub(selectedHub.value.id);
    toast.add({ title: 'Hub deleted', color: 'success' });
    isDeleteOpen.value = false;
    await hubStore.fetchMyHubs();
  } catch {
    toast.add({ title: 'Failed to delete hub', color: 'error' });
  } finally {
    deleteLoading.value = false;
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function sportLabel(sport: string) {
  return sport.charAt(0).toUpperCase() + sport.slice(1);
}

function formatPrice(price: string | null) {
  if (!price) return '—';
  return `₱${parseFloat(price).toFixed(0)}/hr`;
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-[#0f1728]">My Hubs</h1>
        <p class="mt-1 text-sm text-[#64748b]">
          Manage your sports hubs and courts.
        </p>
      </div>
      <UButton
        to="/hubs/create"
        icon="i-heroicons-plus"
        class="bg-[#004e89] font-semibold hover:bg-[#003d6b]"
      >
        Create Hub
      </UButton>
    </div>

    <!-- Loading -->
    <div v-if="hubStore.loading" class="flex items-center gap-2 text-[#64748b]">
      <UIcon name="i-heroicons-arrow-path" class="h-5 w-5 animate-spin" />
      <span class="text-sm">Loading hubs…</span>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!hubStore.myHubs.length"
      class="rounded-2xl border border-dashed border-[#dbe4ef] bg-white p-12 text-center"
    >
      <UIcon
        name="i-heroicons-building-office-2"
        class="mx-auto h-12 w-12 text-[#c8d5e0]"
      />
      <h3 class="mt-4 text-base font-semibold text-[#0f1728]">No hubs yet</h3>
      <p class="mt-1 text-sm text-[#64748b]">
        Create your first hub to start managing courts and bookings.
      </p>
      <UButton
        to="/hubs/create"
        icon="i-heroicons-plus"
        class="mt-5 bg-[#004e89] font-semibold hover:bg-[#003d6b]"
      >
        Create Hub
      </UButton>
    </div>

    <!-- Hub cards grid -->
    <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="hub in hubStore.myHubs"
        :key="hub.id"
        class="flex flex-col overflow-hidden rounded-2xl border border-[#dbe4ef] bg-white shadow-sm"
      >
        <!-- Cover image -->
        <div class="relative h-36 bg-[#e8f0f8]">
          <img
            v-if="hub.cover_image_url"
            :src="hub.cover_image_url"
            :alt="hub.name"
            class="h-full w-full object-cover"
          />
          <div v-else class="flex h-full items-center justify-center">
            <UIcon
              name="i-heroicons-building-office-2"
              class="h-12 w-12 text-[#c8d5e0]"
            />
          </div>
          <!-- Verified badge -->
          <span
            v-if="hub.is_verified"
            class="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-[#004e89] px-2.5 py-1 text-xs font-bold text-white"
          >
            <UIcon name="i-heroicons-check-badge" class="h-3.5 w-3.5" />
            Verified
          </span>
        </div>

        <!-- Body -->
        <div class="flex flex-1 flex-col p-4">
          <div class="flex items-start justify-between gap-2">
            <div>
              <h2 class="font-bold text-[#0f1728] leading-tight">
                {{ hub.name }}
              </h2>
              <p class="mt-0.5 flex items-center gap-1 text-xs text-[#64748b]">
                <UIcon name="i-heroicons-map-pin" class="h-3.5 w-3.5" />
                {{ hub.city }}
              </p>
            </div>
            <div class="flex flex-shrink-0 items-center gap-1">
              <UButton
                :to="`/hubs/${hub.id}/edit`"
                icon="i-heroicons-pencil-square"
                color="neutral"
                variant="ghost"
                size="sm"
                aria-label="Edit hub"
              />
              <UButton
                icon="i-heroicons-trash"
                color="error"
                variant="ghost"
                size="sm"
                aria-label="Delete hub"
                @click="openDelete(hub)"
              />
            </div>
          </div>

          <!-- Stats row -->
          <div class="mt-3 flex items-center gap-3 text-xs text-[#64748b]">
            <span class="flex items-center gap-1">
              <UIcon name="i-heroicons-squares-2x2" class="h-3.5 w-3.5" />
              {{ hub.courts_count }}
              {{ hub.courts_count === 1 ? 'court' : 'courts' }}
            </span>
            <span class="flex items-center gap-1">
              <UIcon name="i-heroicons-currency-dollar" class="h-3.5 w-3.5" />
              from {{ formatPrice(hub.lowest_price_per_hour) }}
            </span>
          </div>

          <!-- Sports -->
          <div v-if="hub.sports.length" class="mt-3 flex flex-wrap gap-1.5">
            <span
              v-for="sport in hub.sports"
              :key="sport"
              class="rounded-full bg-[#e8f0f8] px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[#004e89]"
            >
              {{ sportLabel(sport) }}
            </span>
          </div>

          <!-- View courts link -->
          <div class="mt-auto border-t border-[#f0f4f8] pt-3">
            <NuxtLink
              to="/dashboard/courts"
              class="text-xs font-medium text-[#004e89] hover:underline"
            >
              Manage courts →
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <UModal
      v-model:open="isDeleteOpen"
      title="Delete Hub"
      :ui="{ content: 'max-w-sm' }"
    >
      <template #body>
        <p class="text-sm text-[#0f1728]">
          Are you sure you want to delete
          <strong>{{ selectedHub?.name }}</strong
          >? This will permanently remove the hub and all its courts.
        </p>
        <div class="mt-5 flex justify-end gap-2">
          <UButton color="neutral" variant="ghost" @click="isDeleteOpen = false"
            >Cancel</UButton
          >
          <UButton
            color="error"
            :loading="deleteLoading"
            @click="confirmDelete"
          >
            Delete Hub
          </UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>
