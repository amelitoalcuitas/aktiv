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

const {
  updateProfile,
  canChangeName,
  nextNameChangeDate,
  canChangeUsername,
  nextUsernameChangeDate
} = useProfile();

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

// ── form state ──────────────────────────────────────────────
const form = reactive({
  username: props.user.username ?? '',
  first_name: props.user.first_name,
  last_name: props.user.last_name,
  contact_number: props.user.contact_number ?? '',
  bio: props.user.bio ?? '',
  links: socialLinksToRows(props.user.social_links) as SocialLink[]
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
      error.value = '';
    }
  }
);

// ── computed restrictions ────────────────────────────────────
const nameLocked = computed(() => !canChangeName(props.user));
const usernameLocked = computed(() => !canChangeUsername(props.user));

function formatDate(d: Date | null): string {
  if (!d) return '';
  return d.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
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

// ── social links ─────────────────────────────────────────────
function addLink() {
  const used = new Set(form.links.map((l) => l.platform));
  const next = PLATFORM_OPTIONS.find((p) => !used.has(p.value));
  if (next) form.links.push({ platform: next.value, url: '' });
}

function removeLink(index: number) {
  form.links.splice(index, 1);
}

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

// ── url validation ───────────────────────────────────────────
const saving = ref(false);
const error = ref('');
const touched = ref<Set<number>>(new Set());

function isValidUrl(val: string): boolean {
  if (!val.trim()) return true;
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

// ── save ──────────────────────────────────────────────────────
async function save() {
  form.links.forEach((_, i) => touched.value.add(i));
  if (hasUrlErrors.value) return;

  saving.value = true;
  error.value = '';
  try {
    const clean = (v: string) => v.trim() || null;
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

        <!-- Social Links -->
        <div class="border-t border-[var(--aktiv-border)] pt-4">
          <p class="mb-3 font-semibold text-[var(--aktiv-ink)]">
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
              <UDropdownMenu
                :items="
                  availableOptions(row.platform).map((opt) => ({
                    label: opt.label,
                    icon: opt.icon,
                    onSelect: () => {
                      row.platform = opt.value as SocialPlatform;
                    }
                  }))
                "
              >
                <UButton
                  variant="ghost"
                  color="neutral"
                  class="w-9 shrink-0 px-0 justify-center border border-[var(--aktiv-border)]"
                  :aria-label="row.platform"
                >
                  <UIcon
                    :name="iconFor(row.platform)"
                    class="h-4 w-4 text-[var(--aktiv-muted)]"
                  />
                </UButton>
              </UDropdownMenu>

              <div class="min-w-0 flex-1">
                <UInput
                  v-model="row.url"
                  placeholder="https://..."
                  class="w-full"
                  :color="urlError(index) ? 'error' : undefined"
                  @blur="touched.add(index)"
                />
                <p
                  v-if="urlError(index)"
                  class="mt-0.5 text-xs text-[var(--aktiv-danger-fg)]"
                >
                  {{ urlError(index) }}
                </p>
              </div>

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
  </AppModal>
</template>
