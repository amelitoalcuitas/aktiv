import type { BookingDetail } from '~/types/booking';
import { useApi } from '~/utils/api';

export interface DashboardOverviewSummary {
  needs_review_count: number;
  pending_payments_count: number;
  today_confirmed_count: number;
  revenue_today: number;
}

export interface DashboardOverviewHub {
  hub_id: string;
  hub_name: string;
  is_active: boolean;
  needs_review_count: number;
  pending_payments_count: number;
  today_confirmed_count: number;
  revenue_today: number;
}

export interface DashboardOverviewResponse {
  summary: DashboardOverviewSummary;
  hubs: DashboardOverviewHub[];
  action_needed: BookingDetail[];
  today_schedule: BookingDetail[];
}

export function useDashboardOverview() {
  const { apiFetch } = useApi();

  async function fetchDashboardOverview(): Promise<DashboardOverviewResponse> {
    const res = await apiFetch<{ data: DashboardOverviewResponse }>(
      '/dashboard/overview'
    );

    return res.data;
  }

  return { fetchDashboardOverview };
}
