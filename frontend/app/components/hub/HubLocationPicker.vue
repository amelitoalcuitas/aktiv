<script setup lang="ts">
import maplibregl from 'maplibre-gl';

interface PinCoords {
  lat: number | null;
  lng: number | null;
}

const props = defineProps<{
  modelValue: PinCoords;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: PinCoords];
}>();

const mapContainer = ref<HTMLElement | null>(null);
const hasPinned = computed(
  () => props.modelValue.lat !== null && props.modelValue.lng !== null
);

let map: maplibregl.Map | null = null;
let marker: maplibregl.Marker | null = null;

onMounted(() => {
  if (!mapContainer.value) return;

  map = new maplibregl.Map({
    container: mapContainer.value,
    style: 'https://tiles.openfreemap.org/styles/bright',
    center: [121.774, 12.8797], // Philippines default
    zoom: 5
  });

  map.addControl(new maplibregl.NavigationControl(), 'top-right');

  // If a pin position was already set (e.g. edit mode), restore it
  if (props.modelValue.lat !== null && props.modelValue.lng !== null) {
    placeMarker(props.modelValue.lng, props.modelValue.lat);
    map.flyTo({
      center: [props.modelValue.lng, props.modelValue.lat],
      zoom: 15
    });
  } else if (navigator?.geolocation) {
    // Try to center + pin on the user's current position
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        map?.flyTo({ center: [lng, lat], zoom: 15 });
        placeMarker(lng, lat);
        emit('update:modelValue', { lat, lng });
      },
      () => {
        // Permission denied or unavailable — map stays at Philippines default
      },
      { timeout: 8000 }
    );
  }

  map.on('click', (e) => {
    const { lat, lng } = e.lngLat;
    placeMarker(lng, lat);
    emit('update:modelValue', { lat, lng });
  });
});

onUnmounted(() => {
  map?.remove();
  map = null;
  marker = null;
});

function placeMarker(lng: number, lat: number) {
  if (!map) return;
  if (marker) {
    marker.setLngLat([lng, lat]);
  } else {
    marker = new maplibregl.Marker({ color: '#004e89', draggable: true })
      .setLngLat([lng, lat])
      .addTo(map);

    marker.on('dragend', () => {
      const pos = marker!.getLngLat();
      emit('update:modelValue', { lat: pos.lat, lng: pos.lng });
    });
  }
}
</script>

<template>
  <div
    class="relative w-full overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
  >
    <!-- Map container -->
    <div ref="mapContainer" class="h-72 w-full" />

    <!-- Hint overlay — only shown before first pin -->
    <Transition name="fade">
      <div
        v-if="!hasPinned"
        class="pointer-events-none absolute inset-0 flex items-center justify-center"
      >
        <div
          class="flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-sm font-medium text-[var(--aktiv-ink)] shadow-sm ring-1 ring-[var(--aktiv-border)]"
        >
          <UIcon
            name="i-heroicons-map-pin"
            class="h-4 w-4 text-[var(--aktiv-primary)]"
          />
          Click on the map to pin your hub location
        </div>
      </div>
    </Transition>

    <!-- Coordinates badge — shown after pinning -->
    <Transition name="fade">
      <div
        v-if="hasPinned"
        class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-white/90 px-3 py-1 text-xs font-mono text-[var(--aktiv-muted)] shadow-sm ring-1 ring-[var(--aktiv-border)]"
      >
        {{ modelValue.lat?.toFixed(5) }}, {{ modelValue.lng?.toFixed(5) }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
