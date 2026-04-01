<script setup lang="ts">
import type { User, PublicUser } from '~/types/user';
import type { LinkPlatform, LinkRow } from '~/types/links';

const props = defineProps<{
  profile: User | PublicUser;
  isOwn?: boolean;
  editing?: boolean;
  isAuthenticated?: boolean;
}>();

const emit = defineEmits<{
  editProfile: [];
  editInfo: [];
  uploadAvatar: [file: File];
  uploadBanner: [file: File];
  toggleHeart: [];
}>();

const avatarInput = ref<HTMLInputElement | null>(null);
const bannerInput = ref<HTMLInputElement | null>(null);

const cropperOpen = ref(false);
const cropSrc = ref('');
const cropTarget = ref<'avatar' | 'banner' | null>(null);

const cropperAspectRatio = computed(() =>
  cropTarget.value === 'avatar' ? 1 : 16 / 5
);
const cropperStencilShape = computed(() =>
  cropTarget.value === 'avatar' ? 'circle' : 'rectangle'
);

function onAvatarChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  cropSrc.value = URL.createObjectURL(file);
  cropTarget.value = 'avatar';
  cropperOpen.value = true;
  (e.target as HTMLInputElement).value = '';
}

function onBannerChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  cropSrc.value = URL.createObjectURL(file);
  cropTarget.value = 'banner';
  cropperOpen.value = true;
  (e.target as HTMLInputElement).value = '';
}

function onCropConfirm(blob: Blob) {
  const file = new File([blob], 'upload.jpg', { type: 'image/jpeg' });
  if (cropTarget.value === 'avatar') emit('uploadAvatar', file);
  else if (cropTarget.value === 'banner') emit('uploadBanner', file);
  URL.revokeObjectURL(cropSrc.value);
  cropSrc.value = '';
  cropperOpen.value = false;
  cropTarget.value = null;
}

function onCropCancel() {
  URL.revokeObjectURL(cropSrc.value);
  cropSrc.value = '';
  cropperOpen.value = false;
  cropTarget.value = null;
}

const memberSince = computed(() => {
  return new Date(props.profile.created_at).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'long',
    year: 'numeric'
  });
});

const socialPlatforms = computed<LinkRow[]>(() => {
  const links = props.profile.social_links ?? {};
  return (Object.entries(links) as [LinkPlatform, string | null | undefined][])
    .filter(([, url]) => url)
    .map(([platform, url]) => ({ platform, url: url! }));
});

const heartsCount = computed(() => {
  if ('hearts_count' in props.profile)
    return props.profile.hearts_count ?? null;
  return null;
});

const hasHearted = computed(() => {
  if ('has_hearted' in props.profile)
    return (props.profile as PublicUser).has_hearted;
  return false;
});

const showHearts = computed(() => {
  if (props.isOwn) return true;
  const priv =
    (props.profile as PublicUser).privacy ??
    (props.profile as User).profile_privacy;
  return priv?.show_hearts !== false;
});

const name = computed(() => {
  const first = props.profile.first_name;
  const last = props.profile.last_name;
  if (!first && !last) return props.profile.username ? `@${props.profile.username}` : 'Unknown';
  return first + (last ? ` ${last}` : '');
});
</script>

<template>
  <div>
    <!-- Banner + avatar wrapper (relative, NOT overflow-hidden so avatar isn't clipped) -->
    <div class="relative">
      <!-- Banner -->
      <div
        class="relative h-[180px] sm:h-[240px] w-full overflow-hidden bg-[var(--aktiv-border)]"
      >
        <img
          v-if="profile.banner_url"
          :src="profile.banner_url"
          alt="Profile banner"
          class="h-full w-full object-cover"
        />
        <div v-else class="h-full w-full flex items-center justify-center">
          <UIcon
            name="i-heroicons-photo"
            class="h-12 w-12 text-[var(--aktiv-muted)] opacity-30"
          />
        </div>

        <!-- Change banner button (own profile, editing mode) -->
        <button
          v-if="isOwn && editing"
          type="button"
          class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-md bg-black/50 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-black/70 transition"
          @click="bannerInput?.click()"
        >
          <UIcon name="i-heroicons-camera" class="h-3.5 w-3.5" />
          Change banner
        </button>
        <input
          ref="bannerInput"
          type="file"
          accept="image/*"
          class="hidden"
          @change="onBannerChange"
        />
      </div>

      <!-- Centered avatar (outside overflow-hidden banner, overlapping its bottom edge) -->
      <div class="absolute bottom-0 left-1/2 translate-y-1/2 -translate-x-1/2">
        <div class="relative">
          <AppAvatar
            size="full"
            :src="profile.avatar_url"
            :name="name"
            :alt="name"
            :premium="profile.is_premium"
          />
          <button
            v-if="isOwn && editing"
            type="button"
            class="absolute bottom-1 right-1 flex h-8 w-8 items-center justify-center rounded-full bg-[var(--aktiv-primary)] text-white shadow hover:bg-[var(--aktiv-primary-hover)] transition"
            @click="avatarInput?.click()"
          >
            <UIcon name="i-heroicons-camera" class="h-4 w-4" />
          </button>
          <input
            ref="avatarInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="onAvatarChange"
          />
        </div>
      </div>
    </div>

    <!-- Name + bio + meta (centered, with top padding for avatar overlap) -->
    <div class="mx-auto max-w-4xl px-4 md:px-6">
      <div class="flex flex-col items-center pt-16 pb-5 text-center">
        <!-- Name + pencil -->
        <div class="relative flex flex-wrap items-center justify-center gap-2">
          <h1 class="text-xl font-black text-[var(--aktiv-ink)]">
            {{ name }}
          </h1>
          <button
            v-if="isOwn && editing"
            type="button"
            class="flex items-center justify-center h-6 w-6 rounded-full hover:bg-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)]"
            @click="emit('editInfo')"
          >
            <UIcon name="i-heroicons-pencil" class="h-3.5 w-3.5" />
          </button>
        </div>

        <!-- Badges (Premium, Hub Owner, hearts) -->
        <div
          v-if="
            profile.is_premium ||
            profile.is_hub_owner ||
            (showHearts && heartsCount !== null)
          "
          class="mt-1.5 flex flex-wrap items-center justify-center gap-2"
        >
          <UBadge
            v-if="profile.is_premium"
            color="neutral"
            variant="soft"
            class="rounded-full border border-[#f3d27a] bg-[#fff4d6] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#9a6700]"
          >
            Premium
          </UBadge>
          <UBadge v-if="profile.is_hub_owner" color="primary" variant="soft">
            Hub Owner
          </UBadge>
          <UBadge
            v-if="showHearts && heartsCount !== null"
            color="error"
            variant="subtle"
          >
            <UIcon name="i-heroicons-heart-solid" class="h-3 w-3 mr-0.5" />
            {{ heartsCount }}
          </UBadge>
        </div>

        <!-- Social link icons -->
        <div
          v-if="socialPlatforms.length"
          class="mt-2 flex items-center justify-center gap-3"
        >
          <AppLinksList
            :links="socialPlatforms"
            list-class="flex items-center justify-center gap-3"
          />
          <button
            v-if="isOwn && editing"
            type="button"
            class="flex items-center justify-center h-6 w-6 rounded-full hover:bg-[var(--aktiv-border)] text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)]"
            @click="emit('editInfo')"
          >
            <UIcon name="i-heroicons-pencil" class="h-3.5 w-3.5" />
          </button>
        </div>

        <!-- Member since -->
        <div
          class="mt-2 flex items-center justify-center gap-1.5 text-xs text-[var(--aktiv-muted)]"
        >
          <UIcon name="i-heroicons-calendar" class="h-3.5 w-3.5 shrink-0" />
          <span>Member since {{ memberSince }}</span>
        </div>

        <!-- Action buttons -->
        <div class="mt-3 flex items-center gap-2">
          <slot name="actions">
            <UButton
              v-if="!isOwn && showHearts && isAuthenticated"
              :icon="hasHearted ? 'i-heroicons-heart-solid' : 'i-heroicons-heart'"
              :color="hasHearted ? 'error' : 'neutral'"
              :variant="hasHearted ? 'solid' : 'outline'"
              size="sm"
              @click="emit('toggleHeart')"
            >
              {{ hasHearted ? 'Hearted!' : 'Heart this profile' }}
            </UButton>
          </slot>
        </div>
      </div>
    </div>
  </div>

  <AppImageCropperModal
    v-model:open="cropperOpen"
    :src="cropSrc"
    :aspect-ratio="cropperAspectRatio"
    :stencil-shape="cropperStencilShape"
    @confirm="onCropConfirm"
    @update:open="
      (v) => {
        if (!v) onCropCancel();
      }
    "
  />
</template>
