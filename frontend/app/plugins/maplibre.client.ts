import 'maplibre-gl/dist/maplibre-gl.css';

export default defineNuxtPlugin(() => {
  // MapLibre GL JS is client-only (depends on window/WebGL).
  // Importing the CSS here ensures it's included without SSR issues.
});
