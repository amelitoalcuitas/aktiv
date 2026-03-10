<script setup lang="ts">
const route = useRoute();

await navigateTo(`/hubs/${route.params.id}/scheduler`, { replace: true });
</script>
