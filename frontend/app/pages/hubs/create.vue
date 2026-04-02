<script setup lang="ts">
const route = useRoute();

await navigateTo(
  {
    path: '/dashboard/hubs/create',
    query: route.query,
    hash: route.hash
  },
  { replace: true }
);
</script>
