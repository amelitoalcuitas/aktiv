<script setup lang="ts">
import maplibregl from 'maplibre-gl';

const props = defineProps<{
  lat: string | null;
  lng: string | null;
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: maplibregl.Map | null = null;

onMounted(() => {
  if (!mapContainer.value || props.lat == null || props.lng == null) return;

  const lat = parseFloat(props.lat);
  const lng = parseFloat(props.lng);

  map = new maplibregl.Map({
    container: mapContainer.value,
    style: 'https://tiles.openfreemap.org/styles/bright',
    center: [lng, lat],
    zoom: 15
  });

  new maplibregl.Marker({ color: '#004e89' }).setLngLat([lng, lat]).addTo(map);
});

onUnmounted(() => {
  map?.remove();
  map = null;
});
</script>

<template>
  <div
    v-if="lat != null && lng != null"
    class="mt-4 overflow-hidden rounded-xl border border-[var(--aktiv-border)]"
  >
    <div ref="mapContainer" class="h-[220px] w-full" />
  </div>
</template>
