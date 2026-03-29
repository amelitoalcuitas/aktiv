export interface RemoteSelectFetchParams {
  search: string;
  page: number;
  perPage: number;
}

export interface RemoteSelectFetchResult<T> {
  items: T[];
  hasMore: boolean;
}
