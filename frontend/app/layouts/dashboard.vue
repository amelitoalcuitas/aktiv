<template>
  <div class="flex min-h-screen bg-[#f9fdf2]">
    <AppDashboardSidebar />
    <div class="ml-60 flex min-h-screen flex-1 flex-col">
      <main class="flex-1 p-6 md:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
