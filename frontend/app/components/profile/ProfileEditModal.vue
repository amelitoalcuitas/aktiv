<script setup lang="ts">
import type { User, SocialPlatform, SocialLink } from '~/types/user';

const props = defineProps<{
  open: boolean;
  user: User;
}>();

const emit = defineEmits<{
  'update:open': [val: boolean];
  'saved': [user: User];
}>();

const { updateProfile } = useProfile();

const PLATFORM_OPTIONS: {
  value: SocialPlatform;
  label: string;
  icon: string;
}[] = [
  { value: 'facebook', label: 'Facebook', icon: 'i-simple-icons-facebook' },
  { value: 'instagram', label: 'Instagram', icon: 'i-simple-icons-instagram' },
  { value: 'x', label: 'X (Twitter)', icon: 'i-simple-icons-x' },
  { value: 'youtube', label: 'YouTube', icon: 'i-simple-icons-youtube' },
  { value: 'threads', label: 'Threads', icon: 'i-simple-icons-threads' },
  { value: 'other', label: 'Other', icon: 'i-heroicons-globe-alt' }
];

function socialLinksToRows(links: User['social_links']): SocialLink[] {
  if (!links) return [];
  return (
    Object.entries(links) as [SocialPlatform, string | null | undefined][]
  )
    .filter(([, url]) => url)
    .map(([platform, url]) => ({ platform, url: url! }));
}

const form = reactive({
  name: props.user.name,
  phone: props.user.phone ?? '',
  bio: props.user.bio ?? '',
  links: socialLinksToRows(props.user.social_links) as SocialLink[]
});

watch(
  () => props.open,
  (val) => {
    if (val) {
      form.name = props.user.name;
      form.phone = props.user.phone ?? '';
      form.bio = props.user.bio ?? '';
      form.links = socialLinksToRows(props.user.social_links);
      error.value = '';
    }
  }
);

function addLink() {
  const used = new Set(form.links.map((l) => l.platform));
  const next = PLATFORM_OPTIONS.find((p) => !used.has(p.value));
  if (next) form.links.push({ platform: next.value, url: '' });
}

function removeLink(index: number) {
  form.links.splice(index, 1);
}

// Available platforms for a given row (its own + ones not yet taken)
function availableOptions(current: SocialPlatform) {
  const used = new Set(form.links.map((l) => l.platform));
  return PLATFORM_OPTIONS.filter(
    (p) => p.value === current || !used.has(p.value)
  );
}

function iconFor(platform: SocialPlatform) {
  return (
    PLATFORM_OPTIONS.find((p) => p.value === platform)?.icon ??
    'i-heroicons-globe-alt'
  );
}

const canAddMore = computed(() => form.links.length < PLATFORM_OPTIONS.length);

const saving = ref(false);
const error = ref('');
const touched = ref<Set<number>>(new Set());

function isValidUrl(val: string): boolean {
  if (!val.trim()) return true; // empty is fine — row will be ignored on save
  try {
    const url = new URL(val.trim());
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

function urlError(index: number): string {
  if (!touched.value.has(index)) return '';
  const val = form.links[index]?.url ?? '';
  if (!val.trim()) return '';
  return isValidUrl(val) ? '' : 'Enter a valid URL (e.g. https://example.com)';
}

const hasUrlErrors = computed(() =>
  form.links.some((row) => row.url.trim() && !isValidUrl(row.url))
);

async function save() {
  // Touch all rows to surface errors
  form.links.forEach((_, i) => touched.value.add(i));
  if (hasUrlErrors.value) return;

  saving.value = true;
  error.value = '';
  try {
    const clean = (v: string) => v.trim() || null;
    // Build social_links object from rows
    const social_links: Record<string, string | null> = {
      facebook: null,
      instagram: null,
      x: null,
      youtube: null,
      threads: null,
      other: null
    };
    for (const row of form.links) {
      if (row.url.trim()) social_links[row.platform] = row.url.trim();
    }
    const updated = await updateProfile({
      name: form.name.trim(),
      phone: clean(form.phone),
      bio: clean(form.bio),
      social_links
    });
    emit('saved', updated);
    emit('update:open', false);
  } catch {
    error.value = 'Failed to save. Please try again.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <UModal
    :open="open"
    title="Edit Profile"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <div class="space-y-4">
        <UAlert
          v-if="error"
          color="error"
          variant="subtle"
          :description="error"
        />

        <UFormField label="Name" required>
          <UInput v-model="form.name" placeholder="Your name" class="w-full" />
        </UFormField>

        <UFormField label="Phone">
          <UInput
            v-model="form.phone"
            placeholder="+63 912 345 6789"
            class="w-full"
          />
        </UFormField>

        <UFormField label="Bio">
          <UTextarea
            v-model="form.bio"
            placeholder="Tell others a little about yourself…"
            :rows="3"
            :maxlength="500"
            class="w-full"
          />
          <template #hint>
            <span class="text-xs text-[var(--aktiv-muted)]"
              >{{ form.bio.length }}/500</span
            >
          </template>
        </UFormField>

        <div class="border-t border-[var(--aktiv-border)] pt-4">
          <p class="mb-3 text-sm font-semibold text-[var(--aktiv-ink)]">
            Social Links
            <span class="ml-1 text-xs font-normal text-[var(--aktiv-muted)]"
              >(optional)</span
            >
          </p>

          <div class="space-y-2">
            <div
              v-for="(row, index) in form.links"
              :key="index"
              class="flex items-center gap-2"
            >
              <!-- Platform select -->
              <USelect
                :model-value="row.platform"
                :items="availableOptions(row.platform)"
                value-key="value"
                label-key="label"
                class="w-36 shrink-0"
                @update:model-value="
                  (val) => {
                    row.platform = val as SocialPlatform;
                  }
                "
              >
                <template #leading>
                  <UIcon
                    :name="iconFor(row.platform)"
                    class="h-4 w-4 text-[var(--aktiv-muted)]"
                  />
                </template>
              </USelect>

              <!-- URL input -->
              <div class="min-w-0 flex-1">
                <UInput
                  v-model="row.url"
                  placeholder="https://..."
                  class="w-full"
                  :color="urlError(index) ? 'error' : undefined"
                  @blur="touched.add(index)"
                />
                <p v-if="urlError(index)" class="mt-0.5 text-xs text-[var(--aktiv-danger-fg)]">
                  {{ urlError(index) }}
                </p>
              </div>

              <!-- Remove -->
              <button
                type="button"
                class="shrink-0 text-[var(--aktiv-muted)] hover:text-[var(--aktiv-danger-fg)] transition"
                aria-label="Remove"
                @click="removeLink(index)"
              >
                <UIcon name="i-heroicons-x-mark" class="h-4 w-4" />
              </button>
            </div>
          </div>

          <UButton
            v-if="canAddMore"
            type="button"
            variant="ghost"
            color="neutral"
            size="xs"
            icon="i-heroicons-plus"
            class="mt-2"
            @click="addLink"
          >
            Add another
          </UButton>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton
          variant="ghost"
          color="neutral"
          @click="emit('update:open', false)"
        >
          Cancel
        </UButton>
        <UButton :loading="saving" @click="save"> Save changes </UButton>
      </div>
    </template>
  </UModal>
</template>
