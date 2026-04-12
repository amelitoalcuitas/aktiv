import type { Court } from '~/types/hub';
import type { SelectedSlot } from '~/types/booking';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { toValue } from 'vue';

interface UseHubSlotSelectionOptions {
  timeZone?: MaybeRefOrGetter<string | null | undefined>;
}

interface SlotClickPayload {
  court: Court;
  date: Date;
}

interface UseHubSlotSelectionReturn {
  selectedSlots: Ref<SelectedSlot[]>;
  onSlotClick: (payload: SlotClickPayload) => void;
  clearSlots: () => void;
  removeSlots: (slots: SelectedSlot[]) => void;
}

export function useHubSlotSelection(
  options: UseHubSlotSelectionOptions = {}
): UseHubSlotSelectionReturn {
  const selectedSlots = ref<SelectedSlot[]>([]);

  function buildSelectedSlot(courtId: string, slotStart: Date): SelectedSlot {
    return {
      courtId,
      slotStart,
      hubTimezone: toValue(options.timeZone) ?? null
    };
  }

  function onSlotClick({ court, date }: SlotClickPayload): void {
    const clickedTime = date.getTime();
    const existing = selectedSlots.value;
    const hourMs = 3_600_000;

    if (existing.length === 0 || existing[0]!.courtId !== court.id) {
      selectedSlots.value = [buildSelectedSlot(court.id, date)];
      return;
    }

    const sorted = [...existing].sort(
      (a, b) => a.slotStart.getTime() - b.slotStart.getTime()
    );
    const minTime = sorted[0]!.slotStart.getTime();
    const maxTime = sorted[sorted.length - 1]!.slotStart.getTime();

    if (clickedTime >= minTime && clickedTime <= maxTime) {
      if (clickedTime === minTime) {
        selectedSlots.value = sorted.slice(1);
      } else if (clickedTime === maxTime) {
        selectedSlots.value = sorted.slice(0, -1);
      } else {
        selectedSlots.value = [buildSelectedSlot(court.id, date)];
      }
      return;
    }

    if (clickedTime === minTime - hourMs) {
      selectedSlots.value = [buildSelectedSlot(court.id, date), ...sorted];
      return;
    }

    if (clickedTime === maxTime + hourMs) {
      selectedSlots.value = [...sorted, buildSelectedSlot(court.id, date)];
      return;
    }

    selectedSlots.value = [buildSelectedSlot(court.id, date)];
  }

  function clearSlots(): void {
    selectedSlots.value = [];
  }

  function removeSlots(slots: SelectedSlot[]): void {
    const keys = new Set(
      slots.map((slot) => `${slot.courtId}-${slot.slotStart.getTime()}`)
    );

    selectedSlots.value = selectedSlots.value.filter(
      (slot) => !keys.has(`${slot.courtId}-${slot.slotStart.getTime()}`)
    );
  }

  return {
    selectedSlots,
    onSlotClick,
    clearSlots,
    removeSlots
  };
}
