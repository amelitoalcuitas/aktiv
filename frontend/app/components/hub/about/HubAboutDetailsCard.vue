<script setup lang="ts">
import type { Hub } from '~/types/hub';

defineProps<{
  hub: Hub;
  isOwner: boolean;
}>();
</script>

<template>
  <div
    class="overflow-hidden rounded-2xl border border-[var(--aktiv-border)] bg-[var(--aktiv-surface)] divide-y divide-[var(--aktiv-border)]"
  >
    <div v-if="hub.gallery_images.length > 0">
      <HubGallery :images="hub.gallery_images" :hub-name="hub.name" />
    </div>

    <div v-if="hub.description || isOwner" class="p-4 md:p-6">
      <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">About this hub</h2>
      <p
        v-if="hub.description"
        class="mt-1 whitespace-pre-wrap text-base leading-relaxed text-[var(--aktiv-muted)]"
      >
        {{ hub.description }}
      </p>
      <p v-else class="mt-1 text-sm italic text-[var(--aktiv-muted)]">
        No description yet.
      </p>
    </div>

    <div
      v-if="
        (hub.contact_numbers && hub.contact_numbers.length > 0) ||
        (hub.websites && hub.websites.length > 0)
      "
      class="grid grid-cols-2 divide-x divide-[var(--aktiv-border)]"
    >
      <div
        v-if="hub.contact_numbers && hub.contact_numbers.length > 0"
        class="p-4 md:p-6"
      >
        <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">Contact</h2>
        <ul class="mt-2 space-y-1">
          <li
            v-for="(contact, index) in hub.contact_numbers"
            :key="index"
            class="flex items-center gap-2 text-sm text-[var(--aktiv-ink)]"
          >
            <UIcon
              :name="
                contact.type === 'mobile'
                  ? 'i-heroicons-device-phone-mobile'
                  : 'i-heroicons-phone'
              "
              class="h-4 w-4 shrink-0"
            />
            <ULink :href="`tel:${contact.number}`">{{ contact.number }}</ULink>
          </li>
        </ul>
      </div>

      <div
        v-if="hub.websites && hub.websites.length > 0"
        class="p-4 md:p-6"
      >
        <h2 class="text-lg font-bold text-[var(--aktiv-ink)]">Links</h2>
        <AppLinksList
          :links="hub.websites"
          list-class="mt-2 flex flex-wrap items-center gap-3"
          icon-class="h-5 w-5"
        />
      </div>
    </div>
  </div>
</template>
