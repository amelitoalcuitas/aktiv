export type SportType = 'tennis' | 'badminton' | 'basketball' | 'pickleball';

export interface Hub {
  id: string;
  name: string;
  city: string;
  description: string;
  coverImageUrl: string;
  courtsCount: number;
  sports: SportType[];
  lowestPricePerHour: number;
  rating: number;
  reviewsCount: number;
  isOpenNow: boolean;
}
