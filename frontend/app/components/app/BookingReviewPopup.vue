<script setup lang="ts">
import type { UserBooking } from '~/types/booking';

const props = defineProps<{
  booking: UserBooking;
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'submitted'): void;
  (e: 'skipped'): void;
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
});

const { submitHubRating, skipBookingReview } = useHubs();
const toast = useToast();

const confirmingSkip = ref(false);
const selectedRating = ref(0);
const hoveredRating = ref(0);
const comment = ref('');
const reviewImages = ref<File[]>([]);
const submitting = ref(false);

const hubName = computed(() => props.booking.court?.hub?.name ?? 'this hub');
const courtName = computed(() => props.booking.court?.name ?? '');
const hubId = computed(() => props.booking.court?.hub?.id ?? 0);

const bookingDate = computed(() =>
  new Date(props.booking.start_time).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'long',
    month: 'long',
    day: 'numeric'
  })
);

async function handleSubmit() {
  if (!selectedRating.value || !hubId.value) return;
  submitting.value = true;
  try {
    await submitHubRating(
      hubId.value,
      selectedRating.value,
      comment.value || null,
      props.booking.id,
      reviewImages.value.length > 0 ? reviewImages.value : null
    );
    toast.add({ title: 'Thanks for your feedback!', color: 'success' });
    emit('submitted');
    isOpen.value = false;
  } catch {
    toast.add({ title: 'Failed to submit rating', color: 'error' });
  } finally {
    submitting.value = false;
  }
}

async function handleSkip() {
  try {
    await skipBookingReview(props.booking.id);
  } catch {}
  emit('skipped');
  isOpen.value = false;
}
</script>

<template>
  <AppModal
    v-model:open="isOpen"
    :dismissible="false"
    title="How was your experience?"
  >
    <template #body>
      <div class="space-y-4">
        <!-- Hub info -->
        <div class="flex items-center gap-3">
          <img
            v-if="booking.court?.hub?.cover_image_url"
            :src="booking.court.hub.cover_image_url"
            :alt="hubName"
            class="h-12 w-12 rounded-lg object-cover"
          />
          <div
            v-else
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[var(--aktiv-border)]"
          >
            <UIcon
              name="i-heroicons-building-office-2"
              class="h-6 w-6 text-[var(--aktiv-muted)]"
            />
          </div>
          <div>
            <p class="font-semibold text-[var(--aktiv-ink)]">{{ hubName }}</p>
            <p class="text-sm text-[var(--aktiv-muted)]">
              {{ courtName }} · {{ bookingDate }}
            </p>
          </div>
        </div>

        <!-- Star picker -->
        <div>
          <p class="mb-2 text-sm font-medium text-[var(--aktiv-ink)]">
            Rate your experience
          </p>
          <div class="flex gap-1" @mouseleave="hoveredRating = 0">
            <button
              v-for="star in 5"
              :key="star"
              type="button"
              class="transition cursor-pointer"
              @click="selectedRating = star"
              @mouseenter="hoveredRating = star"
            >
              <UIcon
                :name="
                  star <= (hoveredRating || selectedRating)
                    ? 'i-heroicons-star-solid'
                    : 'i-heroicons-star'
                "
                class="h-8 w-8 transition-colors"
                :class="
                  star <= (hoveredRating || selectedRating)
                    ? 'text-[#F0A202]'
                    : 'text-[var(--aktiv-muted)]'
                "
              />
            </button>
          </div>
        </div>

        <!-- Comment -->
        <UTextarea
          v-model="comment"
          placeholder="Tell others about your experience (optional)"
          :rows="3"
          class="w-full"
        />

        <!-- Photos -->
        <div>
          <p class="mb-2 text-sm font-medium text-[var(--aktiv-ink)]">
            Add photos
            <span class="font-normal text-[var(--aktiv-muted)]">(optional, up to 3)</span>
          </p>
          <AppImageUploader
            :model-value="reviewImages"
            :preview-url="null"
            :max-files="3"
            :max-mb="10"
            @update:model-value="(val) => reviewImages = Array.isArray(val) ? val : (val ? [val] : [])"
          />
        </div>
      </div>
    </template>

    <template #footer>
      <!-- Skip confirmation state -->
      <div v-if="confirmingSkip" class="flex w-full flex-col gap-3">
        <p class="text-sm text-[var(--aktiv-ink)]">
          Your review helps others find great hubs — it would mean a lot to the hub owner! Are you sure you want to skip?
        </p>
        <div class="flex justify-end gap-2">
          <UButton variant="ghost" color="neutral" @click="confirmingSkip = false">Go back</UButton>
          <UButton variant="outline" color="neutral" @click="handleSkip">Yes, skip</UButton>
        </div>
      </div>

      <!-- Normal state -->
      <div v-else class="flex w-full items-center justify-between">
        <button
          type="button"
          class="text-sm text-[var(--aktiv-muted)] hover:text-[var(--aktiv-ink)] transition"
          @click="confirmingSkip = true"
        >
          Skip
        </button>
        <UButton
          :loading="submitting"
          :disabled="!selectedRating"
          color="primary"
          @click="handleSubmit"
        >
          Submit
        </UButton>
      </div>
    </template>
  </AppModal>
</template>
