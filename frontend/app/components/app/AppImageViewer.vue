<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    src: string;
    alt?: string;
    imageClass?: string;
    wrapperClass?: string;
    images?: { id: string; url: string; order: number }[];
    index?: number;
  }>(),
  {
    alt: 'Image preview',
    imageClass: '',
    wrapperClass: ''
  }
);

const currentIndex = ref(props.index ?? 0);
const isGallery = computed(() => !!props.images?.length);
const activeSrc = computed(
  () => props.images?.[currentIndex.value]?.url ?? props.src
);

function prev() {
  if (!props.images?.length) return;
  currentIndex.value =
    (currentIndex.value - 1 + props.images.length) % props.images.length;
  resetView();
}

function next() {
  if (!props.images?.length) return;
  currentIndex.value = (currentIndex.value + 1) % props.images.length;
  resetView();
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowLeft') prev();
  else if (e.key === 'ArrowRight') next();
}

const isOpen = ref(false);

watch(isOpen, (open) => {
  if (open) {
    currentIndex.value = props.index ?? 0;
    window.addEventListener('keydown', onKeydown);
  } else {
    window.removeEventListener('keydown', onKeydown);
  }
});
const zoom = ref(1);
const minZoom = 1;
const maxZoom = 12;
const zoomStep = 0.25;
const panX = ref(0);
const panY = ref(0);
const isDragging = ref(false);
const didDrag = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);
const panStartX = ref(0);
const panStartY = ref(0);
const pinchStartDistance = ref(0);
const pinchStartZoom = ref(1);
const imageRef = ref<HTMLImageElement | null>(null);
const isDesktopView = ref(false);

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

function resetView() {
  zoom.value = 1;
  panX.value = 0;
  panY.value = 0;
  isDragging.value = false;
  didDrag.value = false;
  pinchStartDistance.value = 0;
}

function zoomIn() {
  zoom.value = clamp(zoom.value + zoomStep, minZoom, maxZoom);
}

function zoomOut() {
  zoom.value = clamp(zoom.value - zoomStep, minZoom, maxZoom);
  if (zoom.value === 1) {
    panX.value = 0;
    panY.value = 0;
  }
}

function updateDeviceMode() {
  if (typeof window === 'undefined') {
    return;
  }

  isDesktopView.value = window.matchMedia(
    '(pointer: fine) and (min-width: 1024px)'
  ).matches;
}

function getImageResolutionZoom() {
  const image = imageRef.value;

  if (!image) {
    return 1;
  }

  const rendered = image.getBoundingClientRect();

  if (
    !rendered.width ||
    !rendered.height ||
    !image.naturalWidth ||
    !image.naturalHeight
  ) {
    return 1;
  }

  const widthRatio = image.naturalWidth / rendered.width;
  const heightRatio = image.naturalHeight / rendered.height;
  return clamp(Math.max(widthRatio, heightRatio), minZoom, maxZoom);
}

function onDesktopImageClick() {
  if (!isDesktopView.value) {
    return;
  }

  if (didDrag.value) {
    didDrag.value = false;
    return;
  }

  if (zoom.value === 1) {
    zoom.value = getImageResolutionZoom();
    return;
  }

  resetView();
}

function onDragStart(event: MouseEvent) {
  if (zoom.value <= 1) {
    return;
  }

  isDragging.value = true;
  didDrag.value = false;
  dragStartX.value = event.clientX;
  dragStartY.value = event.clientY;
  panStartX.value = panX.value;
  panStartY.value = panY.value;
}

function onDragMove(event: MouseEvent) {
  if (!isDragging.value) {
    return;
  }

  const deltaX = event.clientX - dragStartX.value;
  const deltaY = event.clientY - dragStartY.value;

  if (Math.abs(deltaX) > 2 || Math.abs(deltaY) > 2) {
    didDrag.value = true;
  }

  panX.value = panStartX.value + deltaX;
  panY.value = panStartY.value + deltaY;
}

function onDragEnd() {
  isDragging.value = false;
}

function getTouchDistance(touchA: Touch, touchB: Touch) {
  const dx = touchA.clientX - touchB.clientX;
  const dy = touchA.clientY - touchB.clientY;
  return Math.hypot(dx, dy);
}

function onTouchStart(event: TouchEvent) {
  if (event.touches.length === 2) {
    const touchA = event.touches.item(0);
    const touchB = event.touches.item(1);

    if (!touchA || !touchB) {
      return;
    }

    pinchStartDistance.value = getTouchDistance(touchA, touchB);
    pinchStartZoom.value = zoom.value;
    isDragging.value = false;
    return;
  }

  if (event.touches.length === 1 && zoom.value > 1) {
    const touch = event.touches.item(0);

    if (!touch) {
      return;
    }

    isDragging.value = true;
    dragStartX.value = touch.clientX;
    dragStartY.value = touch.clientY;
    panStartX.value = panX.value;
    panStartY.value = panY.value;
  }
}

function onTouchMove(event: TouchEvent) {
  if (event.touches.length === 2 && pinchStartDistance.value > 0) {
    const touchA = event.touches.item(0);
    const touchB = event.touches.item(1);

    if (!touchA || !touchB) {
      return;
    }

    const currentDistance = getTouchDistance(touchA, touchB);
    const nextZoom =
      (currentDistance / pinchStartDistance.value) * pinchStartZoom.value;
    zoom.value = clamp(nextZoom, minZoom, maxZoom);

    if (zoom.value === 1) {
      panX.value = 0;
      panY.value = 0;
    }

    return;
  }

  if (event.touches.length === 1 && isDragging.value && zoom.value > 1) {
    const touch = event.touches.item(0);

    if (!touch) {
      return;
    }

    panX.value = panStartX.value + (touch.clientX - dragStartX.value);
    panY.value = panStartY.value + (touch.clientY - dragStartY.value);
  }
}

function onTouchEnd(event: TouchEvent) {
  if (event.touches.length < 2) {
    pinchStartDistance.value = 0;
  }

  if (event.touches.length === 0) {
    isDragging.value = false;
    return;
  }

  if (event.touches.length === 1 && zoom.value > 1) {
    const touch = event.touches.item(0);

    if (!touch) {
      return;
    }

    isDragging.value = true;
    dragStartX.value = touch.clientX;
    dragStartY.value = touch.clientY;
    panStartX.value = panX.value;
    panStartY.value = panY.value;
  }
}

function openViewer() {
  isOpen.value = true;
  resetView();
}

function onModalUpdate(open: boolean) {
  if (!open) {
    resetView();
  }
}

onMounted(() => {
  updateDeviceMode();
  window.addEventListener('resize', updateDeviceMode);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateDeviceMode);
});
</script>

<template>
  <div :class="['cursor-pointer', props.wrapperClass]" @click="openViewer">
    <img :src="props.src" :alt="props.alt" :class="props.imageClass" />
  </div>


  <UModal
    v-model:open="isOpen"
    fullscreen
    :ui="{
      body: 'p-0 sm:p-0 flex flex-col h-full border-none',
      content: 'bg-black/95 border-none',
      header: 'border-none',
      footer: 'border-none justify-center'
    }"
    @update:open="onModalUpdate"
  >
    <template #body>
      <div
        class="relative flex h-full w-full flex-col"
        @mousemove="onDragMove"
        @mouseup="onDragEnd"
        @mouseleave="onDragEnd"
      >
        <div
          class="flex h-full w-full items-center justify-center overflow-hidden"
        >
          <img
            ref="imageRef"
            :src="activeSrc"
            :alt="props.alt"
            class="max-h-full max-w-full select-none rounded-md object-contain"
            :class="
              zoom > 1
                ? isDragging
                  ? 'cursor-grabbing'
                  : 'cursor-grab'
                : isDesktopView
                  ? 'cursor-zoom-in'
                  : ''
            "
            :style="{
              transform: `translate3d(${panX}px, ${panY}px, 0) scale(${zoom})`,
              willChange: 'transform'
            }"
            draggable="false"
            @mousedown.prevent="onDragStart"
            @click="onDesktopImageClick"
            @touchstart="onTouchStart"
            @touchmove.prevent="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchEnd"
          />
        </div>

        <!-- Gallery prev/next overlays -->
        <template v-if="isGallery">
          <button
            type="button"
            aria-label="Previous image"
            class="absolute left-3 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition"
            @click.stop="prev"
          >
            <UIcon name="i-heroicons-chevron-left" class="h-6 w-6" />
          </button>
          <button
            type="button"
            aria-label="Next image"
            class="absolute right-3 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition"
            @click.stop="next"
          >
            <UIcon name="i-heroicons-chevron-right" class="h-6 w-6" />
          </button>
          <span class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white">
            {{ currentIndex + 1 }} / {{ props.images!.length }}
          </span>
        </template>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center gap-2">
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-lucide-zoom-out"
          aria-label="Zoom out"
          :disabled="zoom <= minZoom"
          class="text-white hover:bg-white/20 disabled:opacity-40"
          @click="zoomOut"
        />
        <UButton
          color="neutral"
          variant="ghost"
          aria-label="Reset zoom"
          class="text-white hover:bg-white/20"
          @click="resetView"
        >
          {{ Math.round(zoom * 100) }}%
        </UButton>
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-lucide-zoom-in"
          aria-label="Zoom in"
          :disabled="zoom >= maxZoom"
          class="text-white hover:bg-white/20 disabled:opacity-40"
          @click="zoomIn"
        />
      </div>
    </template>
  </UModal>
</template>
