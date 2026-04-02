export const DEFAULT_HUB_TIMEZONE = 'Asia/Manila';

type DateTimeValue = Date | string | number;

interface TimeZoneParts {
  year: number;
  month: number;
  day: number;
  hour: number;
  minute: number;
  second: number;
  weekday?: number;
}

function resolveDate(value: DateTimeValue): Date {
  return value instanceof Date ? value : new Date(value);
}

export function resolveHubTimezone(timezone?: string | null): string {
  if (!timezone) return DEFAULT_HUB_TIMEZONE;

  try {
    new Intl.DateTimeFormat('en-US', { timeZone: timezone }).format(new Date());
    return timezone;
  } catch {
    return DEFAULT_HUB_TIMEZONE;
  }
}

function getTimeZoneParts(value: DateTimeValue, timeZone: string): TimeZoneParts {
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  });

  const parts = formatter.formatToParts(resolveDate(value));
  const partValue = (type: string) =>
    Number(parts.find((part) => part.type === type)?.value ?? 0);
  const weekdayToken = parts.find((part) => part.type === 'weekday')?.value ?? 'Sun';
  const weekdayMap: Record<string, number> = {
    Sun: 0,
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6
  };

  return {
    year: partValue('year'),
    month: partValue('month'),
    day: partValue('day'),
    hour: partValue('hour'),
    minute: partValue('minute'),
    second: partValue('second'),
    weekday: weekdayMap[weekdayToken] ?? 0
  };
}

function getOffsetMs(date: Date, timeZone: string): number {
  const parts = getTimeZoneParts(date, timeZone);
  const asUtc = Date.UTC(
    parts.year,
    parts.month - 1,
    parts.day,
    parts.hour,
    parts.minute,
    parts.second
  );

  return asUtc - date.getTime();
}

export function buildUtcDateFromHubLocalParts(
  localParts: {
    year: number;
    month: number;
    day: number;
    hour?: number;
    minute?: number;
    second?: number;
  },
  timeZone?: string | null
): Date {
  const resolvedTimeZone = resolveHubTimezone(timeZone);
  const hour = localParts.hour ?? 0;
  const minute = localParts.minute ?? 0;
  const second = localParts.second ?? 0;
  const guess = new Date(
    Date.UTC(localParts.year, localParts.month - 1, localParts.day, hour, minute, second)
  );

  const offset = getOffsetMs(guess, resolvedTimeZone);
  const shifted = new Date(guess.getTime() - offset);
  const secondOffset = getOffsetMs(shifted, resolvedTimeZone);

  return secondOffset === offset ? shifted : new Date(shifted.getTime() - (secondOffset - offset));
}

export function buildHubIsoFromDateAndTime(
  date: Date,
  time: string,
  timeZone?: string | null
): string {
  const resolvedTimeZone = resolveHubTimezone(timeZone);
  const dateParts = getTimeZoneParts(date, resolvedTimeZone);
  const [hour = '0', minute = '0'] = time.split(':');

  return buildUtcDateFromHubLocalParts(
    {
      year: dateParts.year,
      month: dateParts.month,
      day: dateParts.day,
      hour: Number(hour),
      minute: Number(minute),
      second: 0
    },
    resolvedTimeZone
  ).toISOString();
}

export function getTodayDateKeyInTimezone(timeZone?: string | null): string {
  const parts = getTimeZoneParts(new Date(), resolveHubTimezone(timeZone));
  return `${parts.year}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;
}

export function getDateKeyInTimezone(value: DateTimeValue, timeZone?: string | null): string {
  const parts = getTimeZoneParts(value, resolveHubTimezone(timeZone));
  return `${parts.year}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;
}

export function getCurrentWeekdayInTimezone(timeZone?: string | null): number {
  return getTimeZoneParts(new Date(), resolveHubTimezone(timeZone)).weekday ?? 0;
}

export function getCurrentMinutesInTimezone(timeZone?: string | null): number {
  const parts = getTimeZoneParts(new Date(), resolveHubTimezone(timeZone));
  return parts.hour * 60 + parts.minute;
}

export function formatInTimezone(
  value: DateTimeValue,
  options: Intl.DateTimeFormatOptions,
  locale = 'en-PH',
  timeZone?: string | null
): string {
  return resolveDate(value).toLocaleString(locale, {
    ...options,
    timeZone: resolveHubTimezone(timeZone)
  });
}

export function formatInHubTimezone(
  value: DateTimeValue,
  options: Intl.DateTimeFormatOptions,
  locale = 'en-PH',
  timeZone?: string | null
): string {
  return formatInTimezone(value, options, locale, timeZone);
}

export function formatInViewerTimezone(
  value: DateTimeValue,
  options: Intl.DateTimeFormatOptions,
  locale = 'en-PH'
): string {
  return resolveDate(value).toLocaleString(locale, options);
}
