// Backend javob shakllari — `backend/app/Modules/*/Http/Resources`
// bilan mos (PublicBranchResource, ServiceResource,
// AppointmentRequestResource).

export type Branch = {
  id: number;
  name: string;
  address: string | null;
  phone: string | null;
  open_time: string | null;
  close_time: string | null;
  lat: string | null;
  lng: string | null;
};

export type Service = {
  id: number;
  name: string;
  price: string;
  price_formatted: string;
  duration_min: number | null;
  type: "exam" | "assembly" | "repair";
  is_active: boolean;
};

export type AppointmentPayload = {
  branch_id: number;
  name: string;
  phone: string;
  preferred_date?: string;
  note?: string;
};

export type ApiEnvelope<T> = {
  data: T;
};
