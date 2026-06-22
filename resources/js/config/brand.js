export const BRAND = {
  nameFa: 'راهبر CRM',
  nameEn: 'Rahbar CRM',
  tagline: 'CRM فارسی چندمجموعه‌ای برای فروش، بازاریابی و مدیریت تیم',
  description: 'راهبر CRM پلتفرم SaaS فارسی برای مدیریت لید، معامله، کمپین، قیف فروش و گزارش — با Workspace جدا، نقش‌های دسترسی و ماژول‌های تکمیلی.',
  siteUrl: import.meta.env.VITE_SITE_URL || 'http://127.0.0.1:8000',
  supportEmail: 'support@rahbarcrm.ir',
  trialDays: 14,
}

export const DEFAULT_SEO = {
  title: `${BRAND.nameFa} | ${BRAND.nameEn} — CRM فارسی فروش و بازاریابی`,
  description: BRAND.description,
  keywords: 'CRM فارسی, راهبر CRM, Rahbar CRM, نرم افزار CRM, مدیریت فروش, قیف فروش, لید, SaaS',
  ogImage: '/marketing/crm-dashboard.png',
}
