<script setup lang="ts">
const route = useRoute();

await navigateTo(
  {
    path: `/dashboard/hubs/${route.params.id}/edit`,
    query: route.query,
    hash: route.hash
  },
  { replace: true }
);
</script>
