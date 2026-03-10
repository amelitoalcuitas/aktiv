<script setup lang="ts">
const route = useRoute();

await navigateTo(`/hubs/${route.params.id}/about`, { replace: true });
</script>
