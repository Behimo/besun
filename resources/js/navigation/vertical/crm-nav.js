export default [
  {
    title: 'داشبورد من',
    icon: { icon: 'tabler-smart-home' },
    to: 'dashboards-home',
  },
  {
    title: 'CRM',
    icon: { icon: 'tabler-chart-arrows' },
    action: 'manage',
    subject: 'CRM',
    children: [
      { title: 'Contacts', to: 'apps-crm-contacts' },
      { title: 'Leads', to: 'apps-crm-leads' },
      { title: 'Deals', to: 'apps-crm-deals' },
      { title: 'Tasks', to: 'apps-crm-tasks' },
      { title: 'Activities', to: 'apps-crm-activities' },
    ],
  },
  {
    title: 'Billing',
    icon: { icon: 'tabler-credit-card' },
    action: 'manage',
    subject: 'all',
    to: 'apps-billing',
  },
  {
    title: 'Front Pages',
    icon: { icon: 'tabler-files' },
    children: [
      { title: 'Landing', to: 'front-pages-landing-page', target: '_blank' },
      { title: 'Pricing', to: 'front-pages-pricing', target: '_blank' },
      { title: 'About', to: 'front-pages-about', target: '_blank' },
      { title: 'Checkout', to: 'front-pages-checkout' },
    ],
  },
]
