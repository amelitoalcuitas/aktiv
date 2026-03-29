<script setup lang="ts">
import type { LinkRow } from '~/types/links';
import {
  iconForLinkPlatform,
  labelForLinkPlatform
} from '~/types/links';

withDefaults(
  defineProps<{
    links: LinkRow[];
    listClass?: string;
    linkClass?: string;
    iconClass?: string;
  }>(),
  {
    listClass: 'flex items-center gap-3',
    linkClass:
      'text-[var(--aktiv-muted)] transition hover:text-[var(--aktiv-primary)]',
    iconClass: 'h-5 w-5'
  }
);
</script>

<template>
  <div v-if="links.length" :class="listClass">
    <a
      v-for="(link, index) in links"
      :key="`${index}-${link.platform}-${link.url}`"
      :href="link.url"
      :title="labelForLinkPlatform(link.platform)"
      target="_blank"
      rel="noopener noreferrer"
      :class="linkClass"
    >
      <UIcon :name="iconForLinkPlatform(link.platform)" :class="iconClass" />
      <span class="sr-only">{{ labelForLinkPlatform(link.platform) }}</span>
    </a>
  </div>
</template>
