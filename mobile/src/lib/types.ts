/** Shapes returned by the Owlvix API. */

export type Project = {
  id: number;
  title: string;
  category: string;
  category_label: string;
  summary: string | null;
  image_url: string | null;
  url: string | null;
  ios_url: string | null;
  android_url: string | null;
  is_published: boolean;
  sort_order: number;
};

export type PublicReview = {
  name: string;
  code: string | null;
  country: string;
  flag: string;
  service: string | null;
  text: string;
  rating: number;
};

export type SiteData = {
  projects: Project[];
  categories: Record<string, string>;
  reviews: { profile_url: string; rating: string; count: number; five_star: number; items: PublicReview[] };
  form: { services: string[]; budgets: string[] };
};

export type Message = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  service: string | null;
  budget: string | null;
  source: 'contact' | 'popup' | 'app';
  source_label: string;
  excerpt: string;
  unread: boolean;
  replies_count: number;
  created_at: string;
};

export type Reply = { id: number; subject: string; body: string; created_at: string };

export type MessageFull = Message & { message_text: string; replies: Reply[] };

export type MessagePage = { data: Message[]; page: number; last_page: number; total: number; unread: number };

export type Dashboard = {
  stats: {
    total: number;
    thisMonth: number;
    lastMonth: number;
    unread: number;
    replyRate: number | null;
    replied: number;
    avgReplyHours: number | null;
    withPhone: number;
  };
  daily: { date: string; popup: number; contact: number }[];
  services: Record<string, number>;
  budgets: Record<string, number>;
  sources: { popup: number; contact: number };
  recent: Message[];
  content: { projects: number; projectsTotal: number; reviews: number; reviewsTotal: number };
};

type Count = { key: string | null; n: number };

export type Traffic = {
  days: number;
  stats: {
    visitors: number;
    views: number;
    perVisitor: number;
    newShare: number;
    enquiries: number;
    conversion: number | null;
    today: number;
    live: number;
  };
  daily: { date: string; new: number; returning: number }[];
  countries: (Count & { name: string; flag: string })[];
  sources: Count[];
  devices: Count[];
  browsers: Count[];
  pages: { path: string; n: number }[];
};

export type Review = {
  id: number;
  name: string;
  country_code: string | null;
  country: string;
  flag: string;
  service: string | null;
  text: string;
  rating: number;
  is_published: boolean;
  in_email: boolean;
  sort_order: number;
};

export type ReviewSettings = {
  reviews_profile_url: string | null;
  reviews_rating: string | null;
  reviews_count: string | null;
  reviews_five_star: string | null;
};

export type ReviewList = { data: Review[]; settings: ReviewSettings; countries: Record<string, string> };

export type ProjectList = { data: Project[]; categories: Record<string, string> };
