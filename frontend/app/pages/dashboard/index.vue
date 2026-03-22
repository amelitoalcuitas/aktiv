<script setup lang="ts">
import { useHubStore } from '~/stores/hub';

definePageMeta({ layout: 'dashboard', middleware: ['auth', 'admin'] });

const hubStore = useHubStore();
const toast = useToast();

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await hubStore.fetchMyHubs();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

const failedImages = ref(new Set<number>());

function onImgError(id: number) {
  failedImages.value = new Set(failedImages.value).add(id);
}

function formatPrice(price: string | null) {
  if (!price) return '—';
  return `₱${parseFloat(price).toFixed(0)}/hr`;
}

const { updateHub } = useHubs();
const togglingHubs = ref(new Set<number>());

async function toggleActive(
  hub: { id: number; is_active: boolean },
  value: boolean
) {
  togglingHubs.value = new Set(togglingHubs.value).add(hub.id);
  try {
    await updateHub(hub.id, { is_active: value });
    hub.is_active = value;
  } catch {
    toast.add({ title: 'Failed to update hub status', color: 'error' });
  } finally {
    togglingHubs.value.delete(hub.id);
    togglingHubs.value = new Set(togglingHubs.value);
  }
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
    <div
      v-if="!hubStore.initialized || hubStore.loading"
      class="flex items-center gap-2 text-[#64748b]"
    >
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
        class="flex flex-col overflow-hidden rounded-2xl border border-[#dbe4ef] bg-white cursor-pointer transition duration-150 ease-out hover:shadow-md"
        @click="navigateTo(`/hubs/${hub.id}/edit`)"
      >
        <!-- Cover image -->
        <div class="relative h-36 bg-[#e8f0f8]">
          <img
            v-if="hub.cover_image_url && !failedImages.has(hub.id)"
            :src="hub.cover_image_url"
            :alt="hub.name"
            class="h-full w-full object-cover"
            @error="onImgError(hub.id)"
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

            <div class="flex items-center gap-2" @click.stop>
              <span
                class="text-xs font-medium"
                :class="
                  hub.is_active
                    ? 'text-[var(--aktiv-primary)]'
                    : 'text-[var(--aktiv-muted)]'
                "
              >
                {{ hub.is_active ? 'Active' : 'Inactive' }}
              </span>
              <UTooltip
                text="Show or hide this hub from public."
                :delay-duration="200"
              >
                <USwitch
                  :model-value="hub.is_active"
                  :loading="togglingHubs.has(hub.id)"
                  @update:model-value="toggleActive(hub, $event)"
                />
              </UTooltip>
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

          <!-- View courts link -->
          <div class="mt-auto pt-3 w-full justify-end flex gap-4">
            <NuxtLink
              :to="{
                path: '/hubs/' + hub.id
              }"
              class="text-xs font-medium text-[#004e89] hover:underline"
              @click.stop
            >
              View Page
            </NuxtLink>

            <NuxtLink
              :to="{
                path: '/dashboard/courts',
                query: { hubId: String(hub.id) }
              }"
              class="text-xs font-medium text-[#004e89] hover:underline"
              @click.stop
            >
              Manage courts
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
