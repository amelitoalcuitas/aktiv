<script setup lang="ts">
import type { Court } from '~/types/hub';

interface CourtDiscount {
  court_id: string;
  discount_type: string;
  discount_value: string;
}

interface PromoEvent {
  id: string;
  title: string;
  description?: string | null;
  discount_type?: string | null;
  discount_value?: string | null;
  time_from?: string | null;
  time_to?: string | null;
  court_discounts?: CourtDiscount[] | null;
}

const props = defineProps<{
  event: PromoEvent;
  courts?: Court[];
}>();

function formatTime(t?: string | null): string {
  if (!t) return '';
  const [hStr, mStr] = t.split(':');
  const h = parseInt(hStr ?? '0', 10);
  const m = parseInt(mStr ?? '0', 10);
  const period = h >= 12 ? 'PM' : 'AM';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${String(m).padStart(2, '0')} ${period}`;
}

function discountedPrice(originalPrice: string, type: string, value: string): number {
  const price = parseFloat(originalPrice);
  const val = parseFloat(value);
  if (type === 'percent') return price - (price * val) / 100;
  return price - val;
}

function savings(originalPrice: string, type: string, value: string): number {
  const price = parseFloat(originalPrice);
  const val = parseFloat(value);
  if (type === 'percent') return (price * val) / 100;
  return val;
}

const timeRange = computed(() => {
  if (!props.event.time_from || !props.event.time_to) return null;
  return `${formatTime(props.event.time_from)} – ${formatTime(props.event.time_to)}`;
});

// For global discount: pick the first applicable court as a price reference
// For per-court: each card shows its own court price
</script>

<template>
  <div class="mb-4 flex gap-3 rounded-xl border border-[#fde68a] bg-[#fefce8] px-4 py-4">
    <!-- Icon -->
    <div class="mt-0.5 shrink-0">
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#fde68a]">
        <UIcon name="i-heroicons-tag" class="h-5 w-5 text-[#854d0e]" />
      </div>
    </div>

    <!-- Content -->
    <div class="min-w-0 flex-1">
      <p class="text-base font-semibold text-[#854d0e]">{{ event.title }}</p>
      <p v-if="event.description" class="mt-0.5 text-base text-[#92400e]">
        {{ event.description }}
      </p>

      <!-- Per-court discounts with pricing -->
      <div v-if="event.court_discounts?.length" class="mt-2 flex flex-col gap-2">
        <div
          v-for="cd in event.court_discounts"
          :key="cd.court_id"
          class="flex flex-wrap items-center gap-x-4 gap-y-1"
        >
          <span class="font-medium text-[#854d0e]">
            {{ courts?.find((c) => c.id === cd.court_id)?.name ?? 'Court' }}
          </span>

          <!-- Pricing row -->
          <template v-if="courts?.find((c) => c.id === cd.court_id)">
            <div class="flex items-baseline gap-2">
              <span class="text-base line-through text-[#a16207]/60">
                ₱{{ parseFloat(courts!.find((c) => c.id === cd.court_id)!.price_per_hour).toLocaleString('en-PH') }}/hour
              </span>
              <span class="text-xl font-bold text-[#854d0e]">
                ₱{{ discountedPrice(courts!.find((c) => c.id === cd.court_id)!.price_per_hour, cd.discount_type, cd.discount_value).toLocaleString('en-PH') }}/hour
              </span>
              <span class="rounded-full bg-[#fde68a] px-2.5 py-0.5 text-sm font-semibold text-[#854d0e]">
                Save ₱{{ savings(courts!.find((c) => c.id === cd.court_id)!.price_per_hour, cd.discount_type, cd.discount_value).toLocaleString('en-PH') }}
              </span>
            </div>
          </template>
        </div>

        <p v-if="timeRange" class="text-base text-[#92400e]">{{ timeRange }}</p>
      </div>

      <!-- Global discount with pricing -->
      <div v-else-if="event.discount_type && event.discount_value" class="mt-2">
        <div v-if="courts?.length" class="flex flex-col gap-2">
          <div
            v-for="court in courts"
            :key="court.id"
            class="flex flex-wrap items-center gap-x-4 gap-y-1"
          >
            <span class="font-medium text-[#854d0e]">{{ court.name }}</span>
            <div class="flex items-baseline gap-2">
              <span class="text-base line-through text-[#a16207]/60">
                ₱{{ parseFloat(court.price_per_hour).toLocaleString('en-PH') }}/hour
              </span>
              <span class="text-xl font-bold text-[#854d0e]">
                ₱{{ discountedPrice(court.price_per_hour, event.discount_type!, event.discount_value!).toLocaleString('en-PH') }}/hour
              </span>
              <span class="rounded-full bg-[#fde68a] px-2.5 py-0.5 text-sm font-semibold text-[#854d0e]">
                Save ₱{{ savings(court.price_per_hour, event.discount_type!, event.discount_value!).toLocaleString('en-PH') }}
              </span>
            </div>
          </div>
        </div>
        <p v-if="timeRange" class="mt-1 text-base text-[#92400e]">{{ timeRange }}</p>
      </div>
    </div>
  </div>
</template>
