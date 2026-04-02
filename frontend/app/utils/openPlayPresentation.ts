import type { CancelledBy, MyBookingItem } from '~/types/booking';
import type { OpenPlayParticipant, OpenPlaySession, ParticipantPaymentStatus } from '~/types/openPlay';

type OpenPlayDisplayColor =
  | 'primary'
  | 'warning'
  | 'info'
  | 'success'
  | 'error'
  | 'neutral';

type OpenPlayDisplayStatus =
  | 'open'
  | 'full'
  | 'awaiting_receipt'
  | 'pending_venue_confirmation'
  | 'under_review'
  | 'confirmed'
  | 'cancelled'
  | 'expired';

interface OpenPlayDisplayState {
  status: OpenPlayDisplayStatus;
  label: string;
  actionLabel: string;
  helperText: string;
  color: OpenPlayDisplayColor;
}

type PaymentMethodLike = 'pay_on_site' | 'digital_bank' | string | null | undefined;

interface OpenPlayStatusSource {
  paymentStatus: ParticipantPaymentStatus;
  paymentMethod: PaymentMethodLike;
  cancelledBy?: CancelledBy | null;
  expiresAt?: string | null;
}

function isExpiredState(source: OpenPlayStatusSource): boolean {
  return (
    source.paymentStatus === 'cancelled' &&
    source.cancelledBy === 'system' &&
    !!source.expiresAt &&
    new Date(source.expiresAt).getTime() <= Date.now()
  );
}

function buildParticipantDisplay(source: OpenPlayStatusSource): OpenPlayDisplayState {
  if (isExpiredState(source)) {
    return {
      status: 'expired',
      label: 'Expired',
      actionLabel: 'Expired',
      helperText: 'This join expired and the reserved spot was released.',
      color: 'neutral'
    };
  }

  switch (source.paymentStatus) {
    case 'pending_payment':
      if (source.paymentMethod === 'digital_bank') {
        return {
          status: 'awaiting_receipt',
          label: 'Awaiting Receipt',
          actionLabel: 'Upload Receipt',
          helperText: 'Your spot is reserved. Upload your receipt next so the hub can review it.',
          color: 'warning'
        };
      }

      return {
        status: 'pending_venue_confirmation',
        label: 'Pending Venue Confirmation',
        actionLabel: 'Pending Venue Confirmation',
        helperText: 'Your join is still waiting for venue confirmation and is not guaranteed yet.',
        color: 'warning'
      };
    case 'payment_sent':
      return {
        status: 'under_review',
        label: 'Under Review',
        actionLabel: 'Under Review',
        helperText: 'Your receipt is under review.',
        color: 'info'
      };
    case 'confirmed':
      return {
        status: 'confirmed',
        label: 'Confirmed',
        actionLabel: 'Confirmed',
        helperText: 'Your spot is confirmed for this session.',
        color: 'success'
      };
    case 'cancelled':
      return {
        status: 'cancelled',
        label: 'Cancelled',
        actionLabel: 'Cancelled',
        helperText: 'This join has been cancelled.',
        color: 'error'
      };
  }
}

export function getOpenPlayParticipantPresentation(
  participant: Pick<
    OpenPlayParticipant,
    'payment_status' | 'payment_method' | 'cancelled_by' | 'expires_at'
  >
): OpenPlayDisplayState {
  return buildParticipantDisplay({
    paymentStatus: participant.payment_status,
    paymentMethod: participant.payment_method,
    cancelledBy: participant.cancelled_by,
    expiresAt: participant.expires_at
  });
}

export function getOpenPlayBookingPresentation(
  booking: Pick<
    MyBookingItem,
    'status' | 'payment_method' | 'cancelled_by' | 'expires_at'
  >
): OpenPlayDisplayState {
  return buildParticipantDisplay({
    paymentStatus: booking.status,
    paymentMethod: booking.payment_method,
    cancelledBy: booking.cancelled_by,
    expiresAt: booking.expires_at
  });
}

export function getOpenPlaySessionPresentation(
  session: Pick<OpenPlaySession, 'status' | 'viewer_participant'>
): OpenPlayDisplayState {
  if (session.viewer_participant) {
    return getOpenPlayParticipantPresentation(session.viewer_participant);
  }

  if (session.status === 'full') {
    return {
      status: 'full',
      label: 'Full',
      actionLabel: 'Full',
      helperText: 'All spots are currently reserved.',
      color: 'warning'
    };
  }

  return {
    status: 'open',
    label: 'Open',
    actionLabel: 'Join',
    helperText: 'Spots are currently available to join.',
    color: 'primary'
  };
}
