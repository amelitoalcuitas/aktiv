<script setup lang="ts">
import type { Hub } from '~/types/hub';

definePageMeta({ layout: 'hub' });

const route = useRoute();
const { fetchHub } = useHubs();

const hubId = computed(() => String(route.params.id ?? ''));

const { data: hub, error } = await useAsyncData<Hub>(`hub-${hubId.value}`, () =>
  fetchHub(hubId.value)
);

// ── Address helpers ────────────────────────────────────────────────────────
const fullAddress = computed(() => {
  if (!hub.value) return '';
  const parts = [
    hub.value.address,
    hub.value.address_line2,
    hub.value.landmark ? `Near ${hub.value.landmark}` : null,
    hub.value.city,
    hub.value.province,
    hub.value.zip_code,
    hub.value.country
  ].filter(Boolean);
  return parts.join(', ');
});
</script>

<template>
  <div>
    <!-- Error -->
    <UCard
      v-if="error"
      class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
      :ui="{ root: 'ring-0 divide-y-0' }"
    >
      <p class="text-[var(--aktiv-muted)]">Failed to load hub details.</p>
    </UCard>

    <template v-else-if="hub">
      <!-- Gallery carousel -->
      <section v-if="hub.gallery_images.length > 0" class="mb-8">
        <HubGallery :images="hub.gallery_images" :hub-name="hub.name" />
      </section>

      <!-- Details grid -->
      <div class="grid gap-6 md:grid-cols-2">
        <!-- About -->
        <UCard
          class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
          :ui="{ root: 'ring-0 divide-y-0' }"
        >
          <div class="flex items-start gap-4">
            <div
              class="rounded-xl bg-[var(--aktiv-border)] p-3 text-[var(--aktiv-primary)]"
            >
              <UIcon name="i-heroicons-building-storefront" class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
              <h2 class="m-0 text-lg font-black text-[var(--aktiv-ink)]">
                About
              </h2>
              <p
                v-if="hub.description"
                class="mt-2 text-sm leading-relaxed text-[var(--aktiv-muted)]"
              >
                {{ hub.description }}
              </p>
              <p v-else class="mt-2 text-sm text-[var(--aktiv-muted)]">
                No description provided.
              </p>
            </div>
          </div>
        </UCard>

        <!-- Location -->
        <UCard
          class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
          :ui="{ root: 'ring-0 divide-y-0' }"
        >
          <div class="flex items-start gap-4">
            <div
              class="rounded-xl bg-[var(--aktiv-border)] p-3 text-[var(--aktiv-primary)]"
            >
              <UIcon name="i-heroicons-map-pin" class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
              <h2 class="m-0 text-lg font-black text-[var(--aktiv-ink)]">
                Location
              </h2>
              <p class="mt-2 text-sm leading-relaxed text-[var(--aktiv-muted)]">
                {{ fullAddress || 'Address not available.' }}
              </p>
            </div>
          </div>
        </UCard>

        <!-- Sports offered -->
        <UCard
          class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
          :ui="{ root: 'ring-0 divide-y-0' }"
        >
          <div class="flex items-start gap-4">
            <div
              class="rounded-xl bg-[var(--aktiv-border)] p-3 text-[var(--aktiv-primary)]"
            >
              <UIcon name="i-heroicons-trophy" class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
              <h2 class="m-0 text-lg font-black text-[var(--aktiv-ink)]">
                Sports Offered
              </h2>
              <div
                v-if="hub.sports.length > 0"
                class="mt-3 flex flex-wrap gap-2"
              >
                <UBadge
                  v-for="sport in hub.sports"
                  :key="sport"
                  variant="soft"
                  color="primary"
                  class="capitalize"
                >
                  {{ sport }}
                </UBadge>
              </div>
              <p v-else class="mt-2 text-sm text-[var(--aktiv-muted)]">
                No sports listed.
              </p>
            </div>
          </div>
        </UCard>

        <!-- Courts summary -->
        <UCard
          class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm"
          :ui="{ root: 'ring-0 divide-y-0' }"
        >
          <div class="flex items-start gap-4">
            <div
              class="rounded-xl bg-[var(--aktiv-border)] p-3 text-[var(--aktiv-primary)]"
            >
              <UIcon name="i-heroicons-rectangle-group" class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
              <h2 class="m-0 text-lg font-black text-[var(--aktiv-ink)]">
                Courts
              </h2>
              <div class="mt-3 flex gap-6">
                <div>
                  <p class="text-2xl font-black text-[var(--aktiv-primary)]">
                    {{ hub.courts_count }}
                  </p>
                  <p class="text-xs text-[var(--aktiv-muted)]">Total courts</p>
                </div>
                <div v-if="hub.lowest_price_per_hour">
                  <p class="text-2xl font-black text-[var(--aktiv-primary)]">
                    ₱{{
                      parseFloat(hub.lowest_price_per_hour).toLocaleString(
                        'en-PH'
                      )
                    }}
                  </p>
                  <p class="text-xs text-[var(--aktiv-muted)]">Starting / hr</p>
                </div>
              </div>
            </div>
          </div>
        </UCard>

        <!-- Owner -->
        <UCard
          v-if="hub.owner"
          class="rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] p-6 shadow-sm md:col-span-2"
          :ui="{ root: 'ring-0 divide-y-0' }"
        >
          <div class="flex items-center gap-4">
            <UAvatar
              :src="hub.owner.avatar_url ?? undefined"
              :alt="hub.owner.name"
              size="lg"
            />
            <div>
              <p
                class="text-xs font-semibold uppercase tracking-wide text-[var(--aktiv-muted)]"
              >
                Hub Owner
              </p>
              <p class="mt-0.5 text-base font-bold text-[var(--aktiv-ink)]">
                {{ hub.owner.name }}
              </p>
            </div>
          </div>
        </UCard>
      </div>
    </template>

    <!-- Loading skeleton -->
    <template v-else>
      <div class="space-y-4">
        <USkeleton class="h-64 w-full rounded-2xl" />
        <div class="grid gap-6 md:grid-cols-2">
          <USkeleton v-for="n in 4" :key="n" class="h-32 rounded-2xl" />
        </div>
      </div>
    </template>
  </div>
</template>
