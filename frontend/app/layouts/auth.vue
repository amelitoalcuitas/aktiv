<template>
  <div class="flex min-h-screen items-center justify-center bg-[#f9fdf2] px-4">
    <div class="w-full max-w-[420px]">
      <div class="mb-8 text-center">
        <NuxtLink
          to="/"
          class="text-2xl font-extrabold tracking-tight text-[#004e89]"
        >
          Aktiv
        </NuxtLink>
      </div>
      <slot />
    </div>
  </div>
</template>
