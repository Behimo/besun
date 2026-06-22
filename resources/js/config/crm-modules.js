export const CORE_MODULE_SLUG = 'core-base'

export const CATEGORY_LABELS = {
  core: 'پایه',
  sales: 'فروش',
  operations: 'عملیات',
  analytics: 'گزارش و تحلیل',
  communication: 'ارتباطات',
  finance: 'مالی',
  support: 'پشتیبانی',
  automation: 'اتوماسیون',
  integration: 'یکپارچگی',
}

/** Addon modules shown in nav (mod-sales-funnel excluded — same route as قیف فروش) */
export const ADDON_MODULES = [
  {
    slug: 'mod-web-forms',
    title: 'وب‌فرم',
    icon: 'tabler-forms',
    navRoute: 'apps-crm-web-forms',
    category: 'communication',
    sortOrder: 50,
  },
  {
    slug: 'mod-sms',
    title: 'پنل پیامک',
    icon: 'tabler-message',
    navRoute: 'apps-crm-sms',
    category: 'communication',
    sortOrder: 40,
  },
  {
    slug: 'mod-automation',
    title: 'اتوماسیون',
    icon: 'tabler-robot',
    navRoute: 'apps-crm-automation',
    category: 'automation',
    sortOrder: 80,
  },
  {
    slug: 'mod-projects',
    title: 'پروژه و کارت',
    icon: 'tabler-layout-kanban',
    navRoute: 'apps-crm-projects',
    category: 'operations',
    sortOrder: 20,
  },
  {
    slug: 'mod-ticketing',
    title: 'تیکتینگ',
    icon: 'tabler-ticket',
    navRoute: 'apps-crm-ticketing',
    category: 'support',
    sortOrder: 70,
  },
  {
    slug: 'mod-products',
    title: 'کاتالوگ محصول',
    icon: 'tabler-package',
    navRoute: 'apps-crm-products',
    category: 'sales',
    sortOrder: 55,
  },
  {
    slug: 'mod-invoicing',
    title: 'فاکتور و پرداخت',
    icon: 'tabler-file-invoice',
    navRoute: 'apps-crm-invoicing',
    category: 'finance',
    sortOrder: 60,
  },
  {
    slug: 'mod-reports',
    title: 'گزارش و هدف‌گذاری',
    icon: 'tabler-report-analytics',
    navRoute: 'apps-crm-reports',
    category: 'analytics',
    sortOrder: 30,
  },
  {
    slug: 'mod-bi',
    title: 'هوش تجاری BI',
    icon: 'tabler-chart-dots-3',
    navRoute: 'apps-crm-bi',
    category: 'analytics',
    sortOrder: 100,
  },
  {
    slug: 'mod-integrations',
    title: 'یکپارچگی',
    icon: 'tabler-plug-connected',
    navRoute: 'apps-crm-integrations',
    category: 'integration',
    sortOrder: 90,
  },
]

export const CRM_BASE_ITEMS = [
  { title: 'مخاطبین', route: 'apps-crm-contacts', icon: 'tabler-address-book', group: 'sales' },
  { title: 'لیدها', route: 'apps-crm-leads', icon: 'tabler-user-search', group: 'marketing' },
  { title: 'قیف فروش', route: 'apps-crm-deals', icon: 'tabler-chart-funnel', group: 'sales' },
  { title: 'تسک‌ها', route: 'apps-crm-tasks', icon: 'tabler-checkbox', group: 'activities' },
  { title: 'ثبت فعالیت', route: 'apps-crm-activities', icon: 'tabler-calendar-event', group: 'activities' },
]

const navItemKey = item => `${item.title}-${item.to?.name ?? item.to ?? ''}`

const coreNavItem = (title, route, icon, hasCore, options = {}) => ({
  title,
  icon: { icon },
  to: route,
  disable: options.disable ?? !hasCore,
  badgeContent: options.badgeContent ?? (!hasCore ? 'قفل' : undefined),
  badgeClass: options.badgeClass ?? 'bg-secondary',
  action: options.action,
  subject: options.subject,
  permission: options.permission,
})

const addonNavItem = (mod, has) => {
  const active = has(mod.slug)

  return {
    title: mod.title,
    icon: { icon: mod.icon },
    to: mod.navRoute,
    disable: false,
    badgeContent: active ? 'فعال' : 'افزونه',
    badgeClass: active ? 'bg-success' : 'bg-info',
  }
}

const navGroup = (title, icon, children) => ({
  title,
  icon: { icon },
  children,
})

export function groupCatalogModules(modules, categories = CATEGORY_LABELS) {
  const grouped = new Map()

  for (const mod of modules) {
    const key = mod.category ?? 'other'
    if (!grouped.has(key))
      grouped.set(key, { key, label: categories[key] ?? key, modules: [] })

    grouped.get(key).modules.push(mod)
  }

  return [...grouped.values()]
    .map(g => ({
      ...g,
      modules: g.modules.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
    }))
    .sort((a, b) => (a.modules[0]?.sort_order ?? 999) - (b.modules[0]?.sort_order ?? 999))
}

const addonPermissionMap = {
  'mod-web-forms': 'web_forms.read',
  'mod-sms': 'sms.read',
  'mod-automation': 'automation.read',
  'mod-products': 'products.read',
  'mod-invoicing': 'invoicing.read',
  'mod-integrations': 'integrations.manage',
  'mod-bi': 'bi.read',
}

function isBiNavAllowed(userData) {
  if (userData?.tenant?.isOwner)
    return true

  return userData?.role === 'owner' || Boolean(userData?.isManager)
}

function canSeeNavItem(item, userData) {
  if (item.disable)
    return true

  if (userData?.tenant?.isOwner)
    return true

  if (item.permission === 'bi.read')
    return isBiNavAllowed(userData)

  if (!item.permission)
    return true

  const perms = userData?.permissions

  // Until session is refreshed, fall back to showing core nav items
  if (!Array.isArray(perms) || perms.length === 0)
    return true

  return perms.includes(item.permission)
}

function filterNavTree(items, userData) {
  return items
    .map(item => {
      if (item.children) {
        const children = filterNavTree(item.children, userData)
        if (!children.length)
          return null

        return { ...item, children }
      }

      return canSeeNavItem(item, userData) ? item : null
    })
    .filter(Boolean)
}

export function buildTenantNav(userData) {
  const hasCore = Boolean(userData?.hasCoreModule)
  const modules = userData?.activeModules ?? []
  const has = slug => modules.includes(slug)
  const isOwner = Boolean(userData?.tenant?.isOwner)
  const canManageUsers = Boolean(userData?.tenant?.canManageUsers)

  const marketingAddons = ADDON_MODULES.filter(m =>
    ['mod-web-forms', 'mod-sms', 'mod-automation'].includes(m.slug))
  const operationsAddons = ADDON_MODULES.filter(m => m.slug === 'mod-projects')
  const supportAddons = ADDON_MODULES.filter(m => m.slug === 'mod-ticketing')
  const salesAddons = ADDON_MODULES.filter(m => m.slug === 'mod-products')
  const financeAddons = ADDON_MODULES.filter(m => m.slug === 'mod-invoicing')
  const analyticsAddons = ADDON_MODULES.filter(m => ['mod-reports', 'mod-bi'].includes(m.slug))
  const integrationAddons = ADDON_MODULES.filter(m => m.slug === 'mod-integrations')

  const addonWithPermission = (mod, hasModule) => ({
    ...addonNavItem(mod, hasModule),
    permission: addonPermissionMap[mod.slug],
  })

  const items = [
    coreNavItem('پیشخوان مجموعه', 'dashboards-crm', 'tabler-smart-home', hasCore),
    coreNavItem('گفتگوی تیم', 'apps-crm-team-chat', 'tabler-messages', hasCore, { permission: 'team_chat.read' }),
    navGroup('بازاریابی', 'tabler-speakerphone', [
      coreNavItem('کمپین‌ها', 'apps-crm-campaigns', 'tabler-speakerphone', hasCore, { permission: 'campaigns.read' }),
      coreNavItem('لیدها', 'apps-crm-leads', 'tabler-user-search', hasCore, { permission: 'leads.read' }),
      coreNavItem('قیف بازاریابی', 'apps-crm-marketing-funnel', 'tabler-layout-kanban', hasCore, { permission: 'marketing_funnel.read' }),
      ...marketingAddons.map(mod => addonWithPermission(mod, has)),
    ]),
    navGroup('فروش', 'tabler-chart-arrows-vertical', [
      coreNavItem('مخاطبین', 'apps-crm-contacts', 'tabler-address-book', hasCore, { permission: 'contacts.read' }),
      coreNavItem('قیف فروش', 'apps-crm-deals', 'tabler-chart-funnel', hasCore, { permission: 'deals.read' }),
      coreNavItem('هدف‌گذاری', 'apps-crm-sales-targets', 'tabler-target', hasCore, {
        action: 'read',
        subject: 'Reports',
        permission: 'reports.sales.read',
      }),
      coreNavItem('گزارش فروش', 'apps-crm-reports', 'tabler-report-analytics', hasCore, {
        action: 'read',
        subject: 'Reports',
        permission: 'reports.sales.read',
      }),
      ...salesAddons.map(mod => addonWithPermission(mod, has)),
    ]),
    navGroup('فعالیت‌ها', 'tabler-checklist', [
      coreNavItem('تسک‌ها', 'apps-crm-tasks', 'tabler-checkbox', hasCore, { permission: 'tasks.read' }),
      coreNavItem('گزارش کار روزانه', 'apps-crm-daily-reports', 'tabler-report', hasCore, { permission: 'daily_reports.read' }),
      coreNavItem('ثبت فعالیت', 'apps-crm-activities', 'tabler-calendar-event', hasCore, { permission: 'activities.read' }),
    ]),
  ]

  if (operationsAddons.length) {
    items.push(navGroup('عملیات', 'tabler-settings-automation', operationsAddons.map(mod => addonNavItem(mod, has))))
  }

  if (supportAddons.length) {
    items.push(navGroup('پشتیبانی', 'tabler-headset', supportAddons.map(mod => addonNavItem(mod, has))))
  }

  if (financeAddons.length) {
    items.push(navGroup('مالی', 'tabler-cash', financeAddons.map(mod => addonWithPermission(mod, has))))
  }

  if (analyticsAddons.length) {
    items.push(navGroup('گزارش و تحلیل', 'tabler-chart-bar', analyticsAddons.map(mod => addonWithPermission(mod, has))))
  }

  if (integrationAddons.length) {
    items.push(navGroup('یکپارچگی', 'tabler-plug-connected', integrationAddons.map(mod => addonNavItem(mod, has))))
  }

  const managementChildren = []

  if (canManageUsers || isOwner) {
    managementChildren.push(coreNavItem('کاربران و دعوت', 'apps-crm-users', 'tabler-users', hasCore, { permission: 'users.manage' }))
  }

  if (isOwner) {
    managementChildren.push({
      title: 'تنظیمات مجموعه',
      icon: { icon: 'tabler-settings' },
      to: 'apps-tenant-settings',
      action: 'manage',
      subject: 'TenantSettings',
      permission: 'tenant.settings',
    })
  }

  managementChildren.push({
    title: 'خروج از مجموعه',
    icon: { icon: 'tabler-logout' },
    to: 'tenant-exit',
  })

  items.push(navGroup('مدیریت', 'tabler-building', managementChildren))

  return filterNavTree(items, userData)
}

export { navItemKey }
