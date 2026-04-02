<script setup lang="ts">
import type { User } from '~/types/user';
import type { LinkPlatform, LinkRow } from '~/types/links';
import {
  LINK_PLATFORMS,
  isValidExternalUrl
} from '~/types/links';

const props = defineProps<{
  open: boolean;
  user: User;
}>();

const emit = defineEmits<{
  'update:open': [val: boolean];
  'saved': [user: User];
}>();

const {
  updateProfile,
  canChangeName,
  nextNameChangeDate,
  canChangeUsername,
  nextUsernameChangeDate
} = useProfile();

function socialLinksToRows(links: User['social_links']): LinkRow[] {
  if (!links) return [];
  return (
    Object.entries(links) as [LinkPlatform, string | null | undefined][]
  )
    .filter(([, url]) => url)
    .map(([platform, url]) => ({ platform, url: url! }));
}

// ── form state ──────────────────────────────────────────────
const form = reactive({
  username: props.user.username ?? '',
  first_name: props.user.first_name,
  last_name: props.user.last_name,
  contact_number: props.user.contact_number ?? '',
  bio: props.user.bio ?? '',
  links: socialLinksToRows(props.user.social_links) as LinkRow[]
});

// Tracks which field groups are currently being edited
const editing = ref<Set<'username' | 'name' | 'contact_number' | 'bio'>>(
  new Set()
);

// Snapshot values captured when entering edit mode, used for cancellation only
const snapshot = reactive({
  username: '',
  first_name: '',
  last_name: '',
  contact_number: '',
  bio: ''
});

function isEditing(field: 'username' | 'name' | 'contact_number' | 'bio') {
  return editing.value.has(field);
}

function startEdit(field: 'username' | 'name' | 'contact_number' | 'bio') {
  // Save snapshot for cancel
  if (field === 'username') snapshot.username = form.username;
  if (field === 'name') {
    snapshot.first_name = form.first_name;
    snapshot.last_name = form.last_name;
  }
  if (field === 'contact_number') snapshot.contact_number = form.contact_number;
  if (field === 'bio') snapshot.bio = form.bio;

  editing.value = new Set(editing.value).add(field);
}

function cancelEdit(field: 'username' | 'name' | 'contact_number' | 'bio') {
  // Restore snapshot
  if (field === 'username') form.username = snapshot.username;
  if (field === 'name') {
    form.first_name = snapshot.first_name;
    form.last_name = snapshot.last_name;
  }
  if (field === 'contact_number') form.contact_number = snapshot.contact_number;
  if (field === 'bio') form.bio = snapshot.bio;

  const next = new Set(editing.value);
  next.delete(field);
  editing.value = next;
}

function confirmEdit(field: 'username' | 'name' | 'contact_number' | 'bio') {
  // Changes are already live in form via v-model — just close edit mode
  const next = new Set(editing.value);
  next.delete(field);
  editing.value = next;
}

watch(
  () => props.open,
  (val) => {
    if (val) {
      form.username = props.user.username ?? '';
      form.first_name = props.user.first_name;
      form.last_name = props.user.last_name;
      form.contact_number = props.user.contact_number ?? '';
      form.bio = props.user.bio ?? '';
      form.links = socialLinksToRows(props.user.social_links);
      editing.value = new Set();
      touched.value = new Set();
      error.value = '';
    }
  }
);

// ── computed restrictions ────────────────────────────────────
const nameLocked = computed(() => !canChangeName(props.user));
const usernameLocked = computed(() => !canChangeUsername(props.user));

function formatDate(d: Date | null): string {
  if (!d) return '';
  return formatInViewerTimezone(d, {
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });
}

const nameLockedUntil = computed(() =>
  formatDate(nextNameChangeDate(props.user))
);
const usernameLockedUntil = computed(() =>
  formatDate(nextUsernameChangeDate(props.user))
);

// ── url validation ───────────────────────────────────────────
const saving = ref(false);
const error = ref('');
const touched = ref<Set<number>>(new Set());

function isValidUrl(val: string): boolean {
  return isValidExternalUrl(val);
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

const linkErrors = computed(() =>
  form.links.map((_, index) => urlError(index))
);

// ── save ──────────────────────────────────────────────────────
async function save() {
  form.links.forEach((_, i) => touched.value.add(i));
  if (hasUrlErrors.value) return;

  saving.value = true;
  error.value = '';
  try {
    const clean = (v: string) => v.trim() || null;
    const social_links = Object.fromEntries(
      LINK_PLATFORMS.map((platform) => [platform, null])
    ) as Record<LinkPlatform, string | null>;

    for (const row of form.links) {
      if (row.url.trim()) social_links[row.platform] = row.url.trim();
    }

    const payload: Parameters<typeof updateProfile>[0] = {
      contact_number: clean(form.contact_number),
      bio: clean(form.bio),
      social_links
    };

    if (!nameLocked.value) {
      payload.first_name = form.first_name.trim();
      payload.last_name = form.last_name.trim();
    }

    if (
      !usernameLocked.value &&
      form.username.trim() !== (props.user.username ?? '')
    ) {
      payload.username = form.username.trim() || null;
    }

    const updated = await updateProfile(payload);
    emit('saved', updated);
    emit('update:open', false);
  } catch (e: unknown) {
    const err = e as { data?: { message?: string } };
    error.value = err?.data?.message ?? 'Failed to save. Please try again.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <AppModal
    :open="open"
    title="Edit Profile"
    confirm="Save changes"
    :confirm-loading="saving"
    @update:open="emit('update:open', $event)"
    @confirm="save"
    @cancel="emit('update:open', false)"
  >
    <template #body>
      <div class="space-y-5">
        <UAlert
          v-if="error"
          color="error"
          variant="subtle"
          :description="error"
        />

        <!-- Username -->
        <div>
          <p
            class="mb-1 text-xs font-medium text-[var(--aktiv-muted)] uppercase tracking-wide"
          >
            Username
          </p>
          <div v-if="usernameLocked" class="text-[var(--aktiv-muted)]">
            @{{ user.username }}
            <p class="mt-0.5 text-xs text-[var(--aktiv-muted)]">
              You can change your username again on
              <strong>{{ usernameLockedUntil }}</strong
              >.
            </p>
          </div>
          <template v-else>
            <div v-if="isEditing('username')" class="flex items-center gap-2">
              <UInput
                v-model="form.username"
                placeholder="yourhandle"
                class="flex-1"
                icon="i-heroicons-at-symbol"
              />
              <UButton
                icon="i-heroicons-check"
                variant="ghost"
                color="primary"
                size="sm"
                @click="confirmEdit('username')"
              />
              <UButton
                icon="i-heroicons-x-mark"
                variant="ghost"
                color="neutral"
                size="sm"
                @click="cancelEdit('username')"
              />
            </div>
            <div v-else class="flex items-center gap-2">
              <span class="text-[var(--aktiv-ink)]"
                >@{{ form.username || '—' }}</span
              >
              <UButton
                icon="i-heroicons-pencil"
                variant="ghost"
                color="neutral"
                size="xs"
                @click="startEdit('username')"
              />
            </div>
            <p class="mt-1 text-xs text-[var(--aktiv-muted)]">
              Changing your username will lock it for <strong>1 month</strong>.
            </p>
          </template>
        </div>

        <!-- First Name + Last Name (one row, shared edit toggle) -->
        <div>
          <p
            class="mb-1 text-xs font-medium text-[var(--aktiv-muted)] uppercase tracking-wide"
          >
            Name
          </p>
          <div v-if="nameLocked">
            {{ user.first_name }} {{ user.last_name }}
            <p class="mt-0.5 text-xs text-[var(--aktiv-muted)]">
              You can change your name again on
              <strong>{{ nameLockedUntil }}</strong
              >.
            </p>
          </div>
          <template v-else>
            <div v-if="isEditing('name')" class="flex items-center gap-2">
              <UInput
                v-model="form.first_name"
                placeholder="Juan"
                class="flex-1"
              />
              <UInput
                v-model="form.last_name"
                placeholder="dela Cruz"
                class="flex-1"
              />
              <UButton
                icon="i-heroicons-check"
                variant="ghost"
                color="primary"
                size="sm"
                @click="confirmEdit('name')"
              />
              <UButton
                icon="i-heroicons-x-mark"
                variant="ghost"
                color="neutral"
                size="sm"
                @click="cancelEdit('name')"
              />
            </div>
            <div v-else class="flex items-center gap-2">
              <span class="text-[var(--aktiv-ink)]">
                {{
                  [form.first_name, form.last_name].filter(Boolean).join(' ') ||
                  '—'
                }}
              </span>
              <UButton
                icon="i-heroicons-pencil"
                variant="ghost"
                color="neutral"
                size="xs"
                @click="startEdit('name')"
              />
            </div>
            <p class="mt-1 text-xs text-[var(--aktiv-muted)]">
              Changing your name will lock it for <strong>3 months</strong>.
            </p>
          </template>
        </div>

        <!-- Contact Number -->
        <div>
          <p
            class="mb-1 text-xs font-medium text-[var(--aktiv-muted)] uppercase tracking-wide"
          >
            Contact Number
          </p>
          <div
            v-if="isEditing('contact_number')"
            class="flex items-center gap-2"
          >
            <UInput
              v-model="form.contact_number"
              placeholder="+63 912 345 6789"
              class="flex-1"
            />
            <UButton
              icon="i-heroicons-check"
              variant="ghost"
              color="primary"
              size="sm"
              @click="confirmEdit('contact_number')"
            />
            <UButton
              icon="i-heroicons-x-mark"
              variant="ghost"
              color="neutral"
              size="sm"
              @click="cancelEdit('contact_number')"
            />
          </div>
          <div v-else class="flex items-center gap-2">
            <span class="text-[var(--aktiv-ink)]">{{
              form.contact_number || '—'
            }}</span>
            <UButton
              icon="i-heroicons-pencil"
              variant="ghost"
              color="neutral"
              size="xs"
              @click="startEdit('contact_number')"
            />
          </div>
        </div>

        <!-- Bio -->
        <div>
          <p
            class="mb-1 text-xs font-medium text-[var(--aktiv-muted)] uppercase tracking-wide"
          >
            Bio
          </p>
          <div v-if="isEditing('bio')" class="space-y-1">
            <UTextarea
              v-model="form.bio"
              placeholder="Tell others a little about yourself…"
              :rows="3"
              :maxlength="500"
              class="w-full"
            />
            <div class="flex items-center justify-between">
              <span class="text-xs text-[var(--aktiv-muted)]"
                >{{ form.bio.length }}/500</span
              >
              <div class="flex gap-1">
                <UButton
                  icon="i-heroicons-check"
                  variant="ghost"
                  color="primary"
                  size="sm"
                  @click="confirmEdit('bio')"
                />
                <UButton
                  icon="i-heroicons-x-mark"
                  variant="ghost"
                  color="neutral"
                  size="sm"
                  @click="cancelEdit('bio')"
                />
              </div>
            </div>
          </div>
          <div v-else class="flex items-start gap-2">
            <span class="flex-1 text-[var(--aktiv-ink)] whitespace-pre-wrap">{{
              form.bio || '—'
            }}</span>
            <UButton
              icon="i-heroicons-pencil"
              variant="ghost"
              color="neutral"
              size="xs"
              @click="startEdit('bio')"
            />
          </div>
        </div>

        <!-- Links -->
        <div class="border-t border-[var(--aktiv-border)] pt-4">
          <p class="mb-3 font-semibold text-[var(--aktiv-ink)]">
            Links
            <span class="ml-1 text-xs font-normal text-[var(--aktiv-muted)]"
              >(optional)</span
            >
          </p>

          <AppLinksEditor
            v-model="form.links"
            :errors="linkErrors"
            placeholder="https://..."
            add-label="Add another"
            @blur="touched.add($event)"
          />
        </div>
      </div>
    </template>
  </AppModal>
</template>
