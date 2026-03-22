<script setup lang="ts">
const props = defineProps<{
  sections: { id: string; label: string }[];
}>();

const activeSection = ref(props.sections[0]?.id ?? '');
const mobileNavOpen = ref(false);

function updateActiveSection() {
  const threshold = window.innerHeight * 0.35;
  let best: string | null = null;
  let bestTop = -Infinity;
  for (const section of props.sections) {
    const el = document.getElementById(section.id);
    if (!el) continue;
    const rect = el.getBoundingClientRect();
    if (rect.top <= threshold && rect.top > bestTop) {
      best = section.id;
      bestTop = rect.top;
    }
  }
  if (best) activeSection.value = best;
}

onMounted(() => {
  updateActiveSection();
  window.addEventListener('scroll', updateActiveSection, { passive: true });
});

onUnmounted(() => {
  window.removeEventListener('scroll', updateActiveSection);
});

function scrollToSection(id: string) {
  const el = document.getElementById(id);
  if (!el) return;
  const top = el.getBoundingClientRect().top + window.scrollY - 140;
  window.scrollTo({ top, behavior: 'smooth' });
  activeSection.value = id;
  mobileNavOpen.value = false;
}
</script>

<template>
  <!-- Desktop: fixed right pill nav -->
  <nav
    class="hidden lg:flex fixed top-1/2 right-6 xl:right-10 -translate-y-1/2 z-30 flex-col gap-0.5 w-35"
    aria-label="Form sections"
  >
    <button
      v-for="section in sections"
      :key="section.id"
      type="button"
      class="group flex items-center gap-2.5 rounded-full px-3 py-1.5 text-left text-xs font-medium transition-colors duration-150"
      :class="
        activeSection === section.id
          ? 'text-[var(--aktiv-primary)] bg-[var(--aktiv-primary)]/10'
          : 'text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)] hover:bg-[var(--aktiv-border)]/40'
      "
      @click="scrollToSection(section.id)"
    >
      <span
        class="h-1.5 w-1.5 shrink-0 rounded-full transition-colors duration-150"
        :class="
          activeSection === section.id
            ? 'bg-[var(--aktiv-primary)]'
            : 'bg-[var(--aktiv-border)] group-hover:bg-[var(--aktiv-muted)]'
        "
      />
      {{ section.label }}
    </button>
  </nav>

  <!-- Mobile: FAB -->
  <button
    type="button"
    class="lg:hidden fixed bottom-20 right-6 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--aktiv-primary)] text-white shadow-lg transition hover:bg-[var(--aktiv-primary-hover)] active:scale-95"
    aria-label="Jump to section"
    @click="mobileNavOpen = true"
  >
    <UIcon name="i-heroicons-list-bullet" class="h-5 w-5" />
  </button>

  <!-- Mobile: Section modal -->
  <UModal v-model:open="mobileNavOpen" title="Jump to Section">
    <template #body>
      <ul class="divide-y divide-[var(--aktiv-border)]">
        <li v-for="section in sections" :key="section.id">
          <button
            type="button"
            class="flex w-full items-center gap-3 px-1 py-3.5 text-sm font-medium text-left transition-colors"
            :class="
              activeSection === section.id
                ? 'text-[var(--aktiv-primary)]'
                : 'text-[var(--aktiv-ink)] hover:text-[var(--aktiv-primary)]'
            "
            @click="scrollToSection(section.id)"
          >
            <span
              class="h-2 w-2 shrink-0 rounded-full transition-colors"
              :class="
                activeSection === section.id
                  ? 'bg-[var(--aktiv-primary)]'
                  : 'bg-[var(--aktiv-border)]'
              "
            />
            {{ section.label }}
          </button>
        </li>
      </ul>
    </template>
  </UModal>
</template>
