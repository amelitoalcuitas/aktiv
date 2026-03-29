<script setup lang="ts">
import { useDebounceFn, useInfiniteScroll } from '@vueuse/core';
import type { RemoteSelectFetchParams, RemoteSelectFetchResult } from '~/types/select';

defineOptions({ inheritAttrs: false });

type SelectItem = Record<string, any>;

const props = withDefaults(
  defineProps<{
    fetchOptions: (
      params: RemoteSelectFetchParams
    ) => Promise<RemoteSelectFetchResult<SelectItem>>;
    staticItems?: SelectItem[];
    labelKey?: string;
    valueKey: string;
    pageSize?: number;
    reloadKey?: string | number | null;
  }>(),
  {
    staticItems: () => [],
    labelKey: 'label',
    pageSize: 20,
    reloadKey: null
  }
);

const attrs = useAttrs();
const slots = useSlots();
const model = defineModel<any>();
const selectMenu = useTemplateRef<any>('selectMenu');

const searchTerm = ref('');
const remoteItems = ref<SelectItem[]>([]);
const isOpen = ref(false);
const hasLoaded = ref(false);
const hasMore = ref(false);
const currentPage = ref(1);
const loadingInitial = ref(false);
const loadingMore = ref(false);
const lastRequestId = ref(0);

const itemCache = shallowRef(new Map<string, SelectItem>());

function getItemKey(item: unknown): string | null {
  if (!item || typeof item !== 'object') return null;
  const value = (item as Record<string, unknown>)[props.valueKey];
  if (value === null || value === undefined) return null;
  return String(value);
}

function getModelKeys(value: unknown): string[] {
  const values = Array.isArray(value) ? value : value != null ? [value] : [];

  return values
    .map((entry) => {
      if (entry && typeof entry === 'object' && !Array.isArray(entry)) {
        const keyedValue = (entry as Record<string, unknown>)[props.valueKey];
        return keyedValue != null ? String(keyedValue) : null;
      }

      return entry != null ? String(entry) : null;
    })
    .filter((entry): entry is string => !!entry);
}

function cacheItems(items: SelectItem[]) {
  const next = new Map(itemCache.value);

  items.forEach((item) => {
    const key = getItemKey(item);
    if (key) {
      next.set(key, item);
    }
  });

  itemCache.value = next;
}

function mergeUniqueItems(groups: SelectItem[][]): SelectItem[] {
  const seen = new Set<string>();
  const merged: SelectItem[] = [];

  groups.flat().forEach((item) => {
    const key = getItemKey(item);
    if (!key || seen.has(key)) return;

    seen.add(key);
    merged.push(item);
  });

  return merged;
}

const selectedItems = computed(() =>
  getModelKeys(model.value)
    .map((key) => itemCache.value.get(key))
    .filter((item): item is SelectItem => !!item)
);

const mergedItems = computed(() =>
  mergeUniqueItems([props.staticItems, selectedItems.value, remoteItems.value])
);

const isLoading = computed(() => loadingInitial.value || loadingMore.value);
const forwardedAttrs = computed(() => {
  const { searchInput, ['search-input']: searchInputKebab, ...rest } = attrs;
  return rest;
});

const searchInput = computed(() => {
  const raw = attrs.searchInput ?? attrs['search-input'];

  if (raw === false) return false;

  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return {
      ...raw,
      loading: isLoading.value || raw.loading === true,
      placeholder: raw.placeholder ?? 'Search...'
    };
  }

  return {
    icon: 'i-heroicons-magnifying-glass',
    placeholder: 'Search...',
    loading: isLoading.value
  };
});

async function fetchPage(page: number, reset = false) {
  if (reset) {
    loadingInitial.value = true;
  } else {
    loadingMore.value = true;
  }

  const requestId = ++lastRequestId.value;

  try {
    const result = await props.fetchOptions({
      search: searchTerm.value.trim(),
      page,
      perPage: props.pageSize
    });

    if (requestId !== lastRequestId.value) return;

    cacheItems(result.items);
    hasMore.value = result.hasMore;
    currentPage.value = page;
    remoteItems.value = reset
      ? mergeUniqueItems([result.items])
      : mergeUniqueItems([remoteItems.value, result.items]);
    hasLoaded.value = true;
  } finally {
    if (requestId === lastRequestId.value) {
      loadingInitial.value = false;
      loadingMore.value = false;
    }
  }
}

async function ensureLoaded() {
  if (hasLoaded.value || loadingInitial.value) return;
  await fetchPage(1, true);
}

async function reloadFirstPage() {
  remoteItems.value = [];
  currentPage.value = 1;
  hasMore.value = false;
  await fetchPage(1, true);
}

const debouncedReload = useDebounceFn(() => {
  if (!isOpen.value && !hasLoaded.value) return;
  void reloadFirstPage();
}, 250);

watch(
  () => props.staticItems,
  (items) => {
    cacheItems(items);
  },
  { immediate: true, deep: true }
);

watch(
  model,
  (value) => {
    if (Array.isArray(value)) {
      cacheItems(
        value.filter(
          (item): item is SelectItem =>
            !!item && typeof item === 'object' && !Array.isArray(item)
        )
      );
      return;
    }

    if (value && typeof value === 'object') {
      cacheItems([value as SelectItem]);
    }
  },
  { immediate: true }
);

watch(searchTerm, () => {
  debouncedReload();
});

watch(
  () => props.reloadKey,
  (next, previous) => {
    if (next === previous || !hasLoaded.value) return;
    void reloadFirstPage();
  }
);

function handleOpen(nextOpen: boolean) {
  isOpen.value = nextOpen;

  if (nextOpen) {
    void ensureLoaded();
  }
}

function handleFocus() {
  void ensureLoaded();
}

function loadNextPage() {
  if (!isOpen.value || !hasMore.value || isLoading.value) return;
  void fetchPage(currentPage.value + 1);
}

onMounted(() => {
  useInfiniteScroll(() => selectMenu.value?.viewportRef, loadNextPage, {
    distance: 24,
    canLoadMore: () => isOpen.value && hasMore.value && !isLoading.value
  });
});
</script>

<template>
  <USelectMenu
    ref="selectMenu"
    v-model="model"
    v-model:search-term="searchTerm"
    :items="mergedItems"
    :label-key="labelKey"
    :value-key="valueKey"
    :search-input="searchInput"
    ignore-filter
    v-bind="forwardedAttrs"
    :loading="isLoading"
    @focusin="handleFocus"
    @update:open="handleOpen"
  >
    <template v-if="slots.leading" #leading="slotProps">
      <slot name="leading" v-bind="slotProps" />
    </template>

    <template v-if="slots.item" #item="{ item }">
      <slot name="item" :item="item" />
    </template>

    <template v-if="slots.itemLabel || slots['item-label']" #item-label="{ item }">
      <slot name="item-label" :item="item" />
    </template>

    <template
      v-if="slots.itemLeading || slots['item-leading']"
      #item-leading="{ item }"
    >
      <slot name="item-leading" :item="item" />
    </template>

    <template
      v-if="slots.itemTrailing || slots['item-trailing']"
      #item-trailing="{ item }"
    >
      <slot name="item-trailing" :item="item" />
    </template>

    <template #empty>
      <slot v-if="slots.empty" name="empty" />
      <span v-else class="text-sm text-[#64748b]">
        {{
          loadingInitial
            ? 'Loading options…'
            : searchTerm.trim()
              ? 'No matches found'
              : 'Type to search…'
        }}
      </span>
    </template>
  </USelectMenu>
</template>
