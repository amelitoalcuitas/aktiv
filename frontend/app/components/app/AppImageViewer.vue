<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    src: string;
    alt?: string;
    imageClass?: string;
    wrapperClass?: string;
  }>(),
  {
    alt: 'Image preview',
    imageClass: '',
    wrapperClass: ''
  }
);

const isOpen = ref(false);
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
const BODY_LOCK_COUNT_ATTR = 'data-image-viewer-lock-count';
const BODY_PREV_OVERFLOW_ATTR = 'data-image-viewer-prev-overflow';

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

function lockBodyScroll() {
  const body = document.body;
  const currentCount = Number(body.getAttribute(BODY_LOCK_COUNT_ATTR) ?? '0');

  if (currentCount === 0) {
    body.setAttribute(BODY_PREV_OVERFLOW_ATTR, body.style.overflow);
    body.style.overflow = 'hidden';
  }

  body.setAttribute(BODY_LOCK_COUNT_ATTR, String(currentCount + 1));
}

function unlockBodyScroll() {
  const body = document.body;
  const currentCount = Number(body.getAttribute(BODY_LOCK_COUNT_ATTR) ?? '0');

  if (currentCount <= 1) {
    const previousOverflow = body.getAttribute(BODY_PREV_OVERFLOW_ATTR) ?? '';
    body.style.overflow = previousOverflow;
    body.removeAttribute(BODY_LOCK_COUNT_ATTR);
    body.removeAttribute(BODY_PREV_OVERFLOW_ATTR);
    return;
  }

  body.setAttribute(BODY_LOCK_COUNT_ATTR, String(currentCount - 1));
}

function openViewer() {
  isOpen.value = true;
  resetView();
}

function closeViewer() {
  isOpen.value = false;
  resetView();
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) {
    closeViewer();
  }
}

onMounted(() => {
  updateDeviceMode();
  window.addEventListener('keydown', onKeydown);
  window.addEventListener('resize', updateDeviceMode);
});

onBeforeUnmount(() => {
  if (isOpen.value) {
    unlockBodyScroll();
  }

  window.removeEventListener('keydown', onKeydown);
  window.removeEventListener('resize', updateDeviceMode);
});

watch(isOpen, (open, wasOpen) => {
  if (open) {
    lockBodyScroll();
    return;
  }

  if (wasOpen) {
    unlockBodyScroll();
  }
});
</script>

<template>
  <div :class="['cursor-zoom-in', props.wrapperClass]" @click="openViewer">
    <img :src="props.src" :alt="props.alt" :class="props.imageClass" />
  </div>

  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-4"
      role="dialog"
      aria-modal="true"
      @mousemove="onDragMove"
      @mouseup="onDragEnd"
      @mouseleave="onDragEnd"
      @click.self="closeViewer"
    >
      <button
        type="button"
        class="absolute right-4 top-4 z-30 rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20"
        aria-label="Close image viewer"
        @click="closeViewer"
      >
        Close
      </button>

      <div
        class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-2"
      >
        <button
          type="button"
          class="rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20 disabled:opacity-40"
          aria-label="Zoom out"
          :disabled="zoom <= minZoom"
          @click="zoomOut"
        >
          -
        </button>
        <button
          type="button"
          class="rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20"
          aria-label="Reset zoom"
          @click="resetView"
        >
          {{ Math.round(zoom * 100) }}%
        </button>
        <button
          type="button"
          class="rounded-full bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/20 disabled:opacity-40"
          aria-label="Zoom in"
          :disabled="zoom >= maxZoom"
          @click="zoomIn"
        >
          +
        </button>
      </div>

      <div
        class="flex h-full w-full items-center justify-center overflow-hidden"
      >
        <img
          ref="imageRef"
          :src="props.src"
          :alt="props.alt"
          class="max-h-full max-w-full select-none object-contain rounded-md"
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
    </div>
  </Teleport>
</template>
