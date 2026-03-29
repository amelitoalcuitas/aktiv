<script setup lang="ts">
import type { SportType } from '~/types/hub';
import type {
  RemoteSelectFetchParams,
  RemoteSelectFetchResult
} from '~/types/select';

const SPORT_OPTIONS: { value: SportType; label: string }[] = [
  {
    value: 'pickleball',
    label: 'Pickleball'
  },
  {
    value: 'badminton',
    label: 'Badminton'
  },
  {
    value: 'tennis',
    label: 'Tennis'
  },
  {
    value: 'basketball',
    label: 'Basketball'
  },
  {
    value: 'volleyball',
    label: 'Volleyball'
  }
];

const ALL_CITIES = '__all__';

const props = defineProps<{
  hasActiveFilters: boolean;
  cityQueryKey?: string | number | null;
  fetchCityOptions: (
    params: RemoteSelectFetchParams
  ) => Promise<RemoteSelectFetchResult<{ label: string; value: string; distance_km?: number | null }>>;
}>();

const emit = defineEmits<{
  apply: [];
  clear: [];
}>();

const search = defineModel<string>('search', { required: true });
const city = defineModel<string>('city', { required: true });
const sports = defineModel<SportType[]>('sports', { required: true });
const openNow = defineModel<boolean>('openNow', { required: true });

const cityItems = computed(() => {
  if (city.value && city.value !== ALL_CITIES) {
    return [
      { label: 'All cities', value: ALL_CITIES },
      { label: city.value, value: city.value }
    ];
  }

  return [{ label: 'All cities', value: ALL_CITIES }];
});

function toggleSport(sport: SportType) {
  if (sports.value.includes(sport)) {
    sports.value = sports.value.filter((s) => s !== sport);
  } else {
    sports.value = [...sports.value, sport];
  }
}
</script>

<template>
  <!-- Search -->
  <div>
    <label
      class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
    >
      Search
    </label>
    <UInput
      v-model="search"
      placeholder="Search City or Venue"
      icon="i-heroicons-magnifying-glass"
      class="w-full"
      @keyup.enter="emit('apply')"
    />
  </div>

  <!-- Location -->
  <div>
    <label
      class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
    >
      Location
    </label>
    <div class="relative">
      <AppRemoteSelectMenu
        v-model="city"
        class="w-full [&_select]:pl-9"
        placeholder="Search city"
        value-key="value"
        label-key="label"
        :page-size="20"
        :reload-key="cityQueryKey"
        :static-items="cityItems"
        :fetch-options="fetchCityOptions"
        :search-input="{ icon: 'i-heroicons-magnifying-glass', placeholder: 'Search city' }"
      >
        <template #item="{ item }">
          <span class="truncate">{{ item.label }}</span>
        </template>
      </AppRemoteSelectMenu>
    </div>
  </div>

  <!-- Sport pill grid -->
  <div>
    <label
      class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#5d7086]"
    >
      Sport
    </label>
    <div class="grid grid-cols-2 gap-2">
      <button
        v-for="sport in SPORT_OPTIONS"
        :key="sport.value"
        type="button"
        class="cursor-pointer rounded-xl border px-3 py-2.5 text-xs font-bold uppercase text-left tracking-wide transition-colors"
        :class="
          sports.includes(sport.value)
            ? 'border-[#004e89] bg-[#004e89] text-white'
            : 'border-[var(--aktiv-border)] bg-white text-[#5d7086] hover:border-[#004e89] hover:text-[#004e89]'
        "
        @click="toggleSport(sport.value)"
      >
        {{ sport.label }}
      </button>
    </div>
  </div>

  <!-- Open now -->
  <div class="flex cursor-pointer items-center justify-between">
    <label class="text-sm font-medium text-[#0f1728]">Open now</label>
    <USwitch v-model="openNow" />
  </div>
</template>
