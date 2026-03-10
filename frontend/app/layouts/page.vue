<template>
  <div class="flex min-h-screen flex-col bg-[var(--aktiv-bg)]">
    <AppHeader />
    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 md:px-8">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
