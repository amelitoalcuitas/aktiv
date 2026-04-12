import type { Court, Hub, HubEvent } from '~/types/hub';
import type { CalendarBooking } from '~/types/booking';
import type { OpenPlaySession } from '~/types/openPlay';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { toValue } from 'vue';

interface UseHubScheduleOptions {
  hubId: MaybeRefOrGetter<string>;
  hub: Ref<Hub | null | undefined>;
  courts: Ref<Court[] | null | undefined>;
}

interface HubScheduleEventGroups {
  closureEvents: ComputedRef<HubEvent[]>;
  announcementEvents: ComputedRef<HubEvent[]>;
  promoEvents: ComputedRef<HubEvent[]>;
  voucherAnnouncementEvents: ComputedRef<HubEvent[]>;
}

interface UseHubScheduleReturn {
  selectedDate: Ref<Date>;
  bookingsMap: Ref<Record<string, CalendarBooking[]>>;
  openPlaySessions: Ref<OpenPlaySession[]>;
  scheduleLoading: Ref<boolean>;
  currentDayEvents: Ref<HubEvent[]>;
  selectedDateEvents: Ref<HubEvent[]>;
  currentDayGroups: HubScheduleEventGroups;
  selectedDateGroups: HubScheduleEventGroups;
  selectedDateIsToday: ComputedRef<boolean>;
  showCurrentDayNoticeSection: ComputedRef<boolean>;
  showSelectedDateNoticeSection: ComputedRef<boolean>;
  filteredOpenPlaySessionsMap: ComputedRef<Record<string, OpenPlaySession>>;
  gridMinTime: ComputedRef<string>;
  gridMaxTime: ComputedRef<string>;
  refreshSchedule: () => Promise<void>;
  refreshOpenPlay: () => Promise<void>;
  refreshCurrentDayEvents: () => Promise<void>;
  handleOpenPlayUpdated: () => Promise<void>;
}

function isVoucherAnnouncement(event: HubEvent): boolean {
  return event.event_type === 'voucher' && event.show_announcement;
}

function createEventGroups(events: ComputedRef<HubEvent[]>): HubScheduleEventGroups {
  return {
    closureEvents: computed(() =>
      events.value.filter((event) => event.event_type === 'closure')
    ),
    announcementEvents: computed(() =>
      events.value.filter((event) => event.event_type === 'announcement')
    ),
    promoEvents: computed(() =>
      events.value.filter((event) => event.event_type === 'promo')
    ),
    voucherAnnouncementEvents: computed(() =>
      events.value.filter((event) => isVoucherAnnouncement(event))
    )
  };
}

export function useHubSchedule(
  options: UseHubScheduleOptions
): UseHubScheduleReturn {
  const { fetchHubBookings } = useBooking();
  const { fetchSessions } = useOpenPlay();
  const { fetchPublicEvents } = useHubEvents();

  const selectedDate = ref(new Date());
  const bookingsMap = ref<Record<string, CalendarBooking[]>>({});
  const openPlaySessions = ref<OpenPlaySession[]>([]);
  const currentDayEvents = ref<HubEvent[]>([]);
  const selectedDateEvents = ref<HubEvent[]>([]);
  const scheduleLoading = ref(false);
  let scheduleLoadRequestId = 0;

  function hubIdValue(): string {
    return toValue(options.hubId);
  }

  function hubTimeZone(): string | null | undefined {
    return options.hub.value?.timezone;
  }

  function formatDateString(date: Date): string {
    return getDateKeyInTimezone(date, hubTimeZone());
  }

  async function refreshSchedule(): Promise<void> {
    if (!options.hub.value?.id || !options.courts.value?.length) {
      bookingsMap.value = {};
      selectedDateEvents.value = [];
      scheduleLoading.value = false;
      return;
    }

    const requestId = ++scheduleLoadRequestId;
    const dateStr = formatDateString(selectedDate.value);
    scheduleLoading.value = true;

    const [nextBookings, nextEvents] = await Promise.all([
      fetchHubBookings(hubIdValue(), {
        date_from: dateStr,
        date_to: dateStr
      }).catch(() => ({} as Record<string, CalendarBooking[]>)),
      fetchPublicEvents(hubIdValue(), {
        date_from: dateStr,
        date_to: dateStr
      }).catch(() => [] as HubEvent[])
    ]);

    if (requestId !== scheduleLoadRequestId) return;

    bookingsMap.value = nextBookings;
    selectedDateEvents.value = nextEvents;
    scheduleLoading.value = false;
  }

  async function refreshOpenPlay(): Promise<void> {
    try {
      openPlaySessions.value = await fetchSessions(hubIdValue());
    } catch {
      openPlaySessions.value = [];
    }
  }

  async function refreshCurrentDayEvents(): Promise<void> {
    if (!options.hub.value?.id) {
      currentDayEvents.value = [];
      return;
    }

    const todayKey = getTodayDateKeyInTimezone(hubTimeZone());

    try {
      currentDayEvents.value = await fetchPublicEvents(hubIdValue(), {
        date_from: todayKey,
        date_to: todayKey
      });
    } catch {
      currentDayEvents.value = [];
    }
  }

  async function handleOpenPlayUpdated(): Promise<void> {
    await Promise.all([refreshSchedule(), refreshOpenPlay()]);
  }

  const selectedDateIsToday = computed(
    () =>
      formatDateString(selectedDate.value) ===
      getTodayDateKeyInTimezone(hubTimeZone())
  );

  const currentDayEventIds = computed(
    () => new Set(currentDayEvents.value.map((event) => event.id))
  );

  const nonDuplicateSelectedDateEvents = computed(() =>
    selectedDateEvents.value.filter(
      (event) => !currentDayEventIds.value.has(event.id)
    )
  );

  const currentDayEventsComputed = computed(() => currentDayEvents.value);
  const selectedDateEventsComputed = computed(
    () => nonDuplicateSelectedDateEvents.value
  );

  const currentDayGroups = createEventGroups(currentDayEventsComputed);
  const selectedDateGroups = createEventGroups(selectedDateEventsComputed);

  const showCurrentDayNoticeSection = computed(
    () =>
      currentDayGroups.closureEvents.value.length > 0 ||
      currentDayGroups.announcementEvents.value.length > 0 ||
      currentDayGroups.promoEvents.value.length > 0 ||
      currentDayGroups.voucherAnnouncementEvents.value.length > 0
  );

  const showSelectedDateNoticeSection = computed(
    () =>
      !selectedDateIsToday.value &&
      (selectedDateGroups.closureEvents.value.length > 0 ||
        selectedDateGroups.announcementEvents.value.length > 0 ||
        selectedDateGroups.promoEvents.value.length > 0 ||
        selectedDateGroups.voucherAnnouncementEvents.value.length > 0)
  );

  const filteredOpenPlaySessionsMap = computed<Record<string, OpenPlaySession>>(
    () => {
      const selectedDateKey = formatDateString(selectedDate.value);

      return Object.fromEntries(
        openPlaySessions.value
          .filter((session) => {
            if (!session.booking) return false;

            return (
              getDateKeyInTimezone(
                session.booking.start_time,
                session.booking.hub_timezone ??
                  session.booking.court?.hub_timezone ??
                  hubTimeZone()
              ) === selectedDateKey
            );
          })
          .map((session) => [session.booking_id, session])
      );
    }
  );

  const gridMinTime = computed(() => {
    const operatingHours = options.hub.value?.operating_hours;
    if (!operatingHours?.length) return '06:00';
    const openDays = operatingHours.filter((entry) => !entry.is_closed);
    if (!openDays.length) return '06:00';
    return openDays.reduce(
      (min, entry) => (entry.opens_at < min ? entry.opens_at : min),
      openDays[0]!.opens_at
    );
  });

  const gridMaxTime = computed(() => {
    const operatingHours = options.hub.value?.operating_hours;
    if (!operatingHours?.length) return '23:00';
    const openDays = operatingHours.filter((entry) => !entry.is_closed);
    if (!openDays.length) return '23:00';
    return openDays.reduce(
      (max, entry) => (entry.closes_at > max ? entry.closes_at : max),
      openDays[0]!.closes_at
    );
  });

  watch(
    () => options.courts.value,
    () => {
      void refreshSchedule();
    },
    { immediate: true }
  );

  watch(selectedDate, () => {
    void refreshSchedule();
  });

  watch(
    () => options.hub.value?.id,
    (hubId) => {
      if (!hubId) return;
      void refreshSchedule();
      void refreshCurrentDayEvents();
      void refreshOpenPlay();
    },
    { immediate: true }
  );

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let hubChannel: any = null;

  onMounted(() => {
    const { $echo } = useNuxtApp();
    if (!$echo) return;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const echo = $echo as any;
    echo.connector.pusher.connection.connect();
    hubChannel = echo.channel(`hub.${hubIdValue()}`);
    hubChannel.listen('.booking.slot.updated', () => {
      void refreshSchedule();
    });
  });

  onUnmounted(() => {
    const { $echo } = useNuxtApp();
    if ($echo && hubChannel) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ($echo as any).leaveChannel(`hub.${hubIdValue()}`);
      hubChannel = null;
    }
  });

  return {
    selectedDate,
    bookingsMap,
    openPlaySessions,
    scheduleLoading,
    currentDayEvents,
    selectedDateEvents,
    currentDayGroups,
    selectedDateGroups,
    selectedDateIsToday,
    showCurrentDayNoticeSection,
    showSelectedDateNoticeSection,
    filteredOpenPlaySessionsMap,
    gridMinTime,
    gridMaxTime,
    refreshSchedule,
    refreshOpenPlay,
    refreshCurrentDayEvents,
    handleOpenPlayUpdated
  };
}
