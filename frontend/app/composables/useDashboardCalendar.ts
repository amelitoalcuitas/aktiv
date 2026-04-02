import { useApi } from '~/utils/api';

export interface DashboardCalendarItem {
  id: string;
  kind: 'event' | 'open_play';
  hub_id: string;
  hub_name: string;
  hub_timezone?: string | null;
  title: string;
  date: string;
  time_label: string | null;
  to: string;
}

export function useDashboardCalendar() {
  const { apiFetch } = useApi();

  async function fetchDashboardCalendar(params: {
    date_from: string;
    date_to: string;
  }): Promise<DashboardCalendarItem[]> {
    const res = await apiFetch<{ data: DashboardCalendarItem[] }>(
      '/dashboard/calendar',
      { query: params }
    );

    return res.data;
  }

  return { fetchDashboardCalendar };
}
