<script setup lang="ts">
const props = defineProps<{
  images: { id: string; url: string; order: number }[];
  hubName?: string;
}>();

const currentIndex = ref(0);
const AUTO_NEXT_MS = 3000;

const carouselRef = ref<{
  emblaApi?: {
    scrollTo: (index: number) => void;
  };
} | null>(null);

function selectImage(index: number) {
  carouselRef.value?.emblaApi?.scrollTo(index);
}

function onSelect(index: number) {
  currentIndex.value = index;
}

watch(
  () => props.images.length,
  (length) => {
    if (length === 0) {
      currentIndex.value = 0;
      return;
    }

    if (currentIndex.value >= length) {
      currentIndex.value = 0;
      nextTick(() => {
        carouselRef.value?.emblaApi?.scrollTo(0);
      });
    }
  },
  { immediate: true }
);
</script>

<template>
  <div class="overflow-hidden bg-[var(--aktiv-surface)]">
    <!-- Main image carousel -->
    <div class="relative">
      <UCarousel
        ref="carouselRef"
        :items="images"
        arrows
        class="aspect-[16/9] w-full"
        autoplay
        :ui="{
          root: 'h-full',
          viewport: 'h-full',
          container: 'h-full'
        }"
        @select="onSelect"
      >
        <template #default="{ item, index }">
          <AppImageViewer
            :src="item.url"
            :alt="`${hubName ?? 'Hub'} gallery image ${index + 1}`"
            wrapper-class="h-full w-full"
            image-class="h-full w-full object-contain"
            :images="images"
            :index="index"
          />
        </template>
      </UCarousel>

      <!-- Counter badge -->
      <span
        class="absolute bottom-3 right-3 rounded-full bg-black/50 px-3 py-1 text-xs font-bold text-white"
      >
        {{ currentIndex + 1 }} / {{ images.length }}
      </span>
    </div>

    <!-- Thumbnail strip -->
    <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto p-3">
      <button
        v-for="(img, i) in images"
        :key="img.id"
        type="button"
        class="h-14 w-20 shrink-0 overflow-hidden rounded-lg border-2 transition"
        :class="
          i === currentIndex
            ? 'border-[var(--aktiv-primary)]'
            : 'border-transparent opacity-60 hover:opacity-100'
        "
        :aria-label="`View gallery image ${i + 1}`"
        @click="selectImage(i)"
      >
        <img
          :src="img.url"
          :alt="`Thumbnail ${i + 1}`"
          class="h-full w-full object-cover"
        />
      </button>
    </div>
  </div>
</template>
