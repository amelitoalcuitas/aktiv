<template>
  <div class="flex min-h-screen items-center justify-center bg-[#f9fdf2] px-4">
    <div class="w-full max-w-[420px]">
      <div class="mb-8 flex flex-col items-center text-center">
        <NuxtLink to="/">
          <AppLogo class="h-10 w-auto" />
        </NuxtLink>
        <p class="mt-3 text-sm text-[#64748b]">Find courts. Join sessions. Play more.</p>
      </div>
      <slot />
    </div>
  </div>
</template>
