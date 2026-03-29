<script setup lang="ts">
import type { HubContactNumber, HubWebsite } from '~/types/hub';

defineProps<{
  contactNumbers: HubContactNumber[];
  websites: HubWebsite[];
}>();
</script>

<template>
  <UAlert
    color="secondary"
    variant="soft"
    icon="i-heroicons-information-circle"
    title="Confirm availability with the venue"
  >
    <template #description>
      <span>
        We recommend contacting the venue directly to confirm slot availability
        before booking, as the schedule may not always reflect real-time
        updates.
      </span>

      <div
        v-if="contactNumbers.length > 0"
        class="mt-2 flex flex-wrap gap-x-5 gap-y-1.5"
      >
        <span
          v-for="(cn, i) in contactNumbers"
          :key="i"
          class="inline-flex items-center gap-1.5 text-sm"
        >
          <UIcon name="i-heroicons-phone" class="h-4 w-4 shrink-0" />
          <span class="font-medium"
            ><a :href="`tel:${cn.number}`">{{ cn.number }}</a></span
          >
        </span>
      </div>

      <div
        v-if="websites.length > 0"
        class="mt-1.5"
      >
        <AppLinksList
          :links="websites"
          list-class="flex flex-wrap items-center gap-3"
          icon-class="h-4 w-4"
        />
      </div>
    </template>
  </UAlert>
</template>
