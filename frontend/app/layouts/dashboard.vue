<script setup lang="ts">
const sidebarOpen = ref(false);
</script>

<template>
  <div class="flex min-h-screen bg-[#f9fdf2]">
    <AppDashboardSidebar v-model:open="sidebarOpen" />
    <div
      class="flex min-h-screen flex-1 flex-col min-w-0 overflow-hidden md:ml-60"
    >
      <!-- Mobile top bar -->
      <div
        class="flex h-14 items-center border-b border-[#dbe4ef] bg-white px-4 md:hidden"
      >
        <button
          class="flex items-center justify-center rounded-lg p-1.5 text-[#64748b] hover:bg-[#f0f4f8]"
          aria-label="Open sidebar"
          @click="sidebarOpen = true"
        >
          <UIcon name="i-heroicons-bars-3" class="h-5 w-5" />
        </button>
        <AppLogo class="ml-3 h-5 w-auto" />
        <span
          class="ml-2 rounded-md bg-[#e8f0f8] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#004e89]"
        >
          Dashboard
        </span>
      </div>
      <main class="flex-1 min-w-0 p-6 md:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
