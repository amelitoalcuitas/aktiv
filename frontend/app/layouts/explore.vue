<template>
  <div>
    <header class="absolute inset-x-0 top-0 z-30">
      <div
        class="mx-auto flex h-[76px] w-full max-w-[1280px] items-center px-4 md:px-8"
      >
        <NuxtLink
          to="/explore"
          class="inline-flex items-center gap-2.5"
          aria-label="Aktiv home"
        >
          <span
            class="text-xl font-extrabold tracking-tight text-[var(--aktiv-primary)]"
          >
            Aktiv
          </span>
        </NuxtLink>
      </div>
    </header>
    <main>
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
