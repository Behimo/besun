const emailRouteComponent = () => import('@/pages/apps/email/index.vue')
const publicWebFormComponent = () => import('@/pages/public/forms/[token].vue')
import { isPlatformStaffSession, resolveCustomerPostLoginRoute, resolvePlatformPostLoginRoute } from '@/composables/usePlatformAdmin'
import { expireAuthSessionIfNeeded, isLoggedIn } from '@/utils/authSession'

// 👉 Redirects
export const redirects = [
  {
    path: '/',
    name: 'index',
    meta: { public: true },
    redirect: to => {
      const expiredRoute = expireAuthSessionIfNeeded(null)

      if (expiredRoute)
        return expiredRoute

      const userData = useCookie('userData')

      if (isLoggedIn()) {
        if (isPlatformStaffSession(userData.value))
          return resolvePlatformPostLoginRoute(userData.value)

        return resolveCustomerPostLoginRoute()
      }

      return { path: '/home', query: to.query }
    },
  },
  {
    path: '/register',
    redirect: () => ({ name: 'login' }),
  },
  {
    path: '/front-pages/landing-page',
    redirect: () => ({ path: '/home' }),
  },
  {
    path: '/pages/user-profile',
    name: 'pages-user-profile',
    redirect: () => ({ name: 'pages-user-profile-tab', params: { tab: 'profile' } }),
  },
  {
    path: '/pages/account-settings',
    name: 'pages-account-settings',
    redirect: () => ({ name: 'pages-account-settings-tab', params: { tab: 'account' } }),
  },
]
export const routes = [
  // Email filter
  {
    path: '/apps/email/filter/:filter',
    name: 'apps-email-filter',
    component: emailRouteComponent,
    meta: {
      navActiveLink: 'apps-email',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },

  // Email label
  {
    path: '/apps/email/label/:label',
    name: 'apps-email-label',
    component: emailRouteComponent,
    meta: {
      // contentClass: 'email-application',
      navActiveLink: 'apps-email',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  {
    path: '/dashboards/logistics',
    name: 'dashboards-logistics',
    component: () => import('@/pages/apps/logistics/dashboard.vue'),
  },
  {
    path: '/dashboards/academy',
    name: 'dashboards-academy',
    component: () => import('@/pages/apps/academy/dashboard.vue'),
  },
  {
    path: '/apps/ecommerce/dashboard',
    name: 'apps-ecommerce-dashboard',
    component: () => import('@/pages/dashboards/ecommerce.vue'),
  },
  {
    path: '/public/forms/:token',
    name: 'public-forms-token',
    component: publicWebFormComponent,
    meta: {
      public: true,
      layout: 'blank',
    },
  },
]
