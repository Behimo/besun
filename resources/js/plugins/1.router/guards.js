import { useAbility } from '@casl/vue'
import { canNavigate } from '@layouts/plugins/casl'
import { syncManagerBiAbility } from '@/utils/biAbility'
import { expireAuthSessionIfNeeded, isLoggedIn } from '@/utils/authSession'
import {
  isPlatformStaffSession,
  platformLoginRouteForPortal,
  resolveCustomerPostLoginRoute,
  resolvePlatformPostLoginRoute,
} from '@/composables/usePlatformAdmin'

const isPlatformRoute = to => String(to.path).startsWith('/apps/platform')
const isAdminLoginRoute = to => to.path === '/admin/login' || to.name === 'admin-login'
const isSupportLoginRoute = to => to.path === '/support/login' || to.name === 'support-login'

export const setupGuards = router => {
  router.beforeEach(to => {
    if (to.meta.public)
      return

    const ability = useAbility()
    const expiredRoute = expireAuthSessionIfNeeded(ability)

    if (expiredRoute)
      return expiredRoute

    const userData = useCookie('userData').value
    const loggedIn = isLoggedIn()
    const isPlatformStaff = isPlatformStaffSession(userData)

    if (to.meta.unauthenticatedOnly) {
      if (! loggedIn)
        return undefined

      if (to.meta.platformPortal) {
        return resolvePlatformPostLoginRoute(userData)
      }

      if (isPlatformStaff) {
        return resolvePlatformPostLoginRoute(userData)
      }

      return resolveCustomerPostLoginRoute()
    }

    if (loggedIn && isPlatformRoute(to) && ! isPlatformStaff) {
      return platformLoginRouteForPortal('admin')
    }

    if (loggedIn && ! isPlatformStaff && isAdminLoginRoute(to))
      return resolveCustomerPostLoginRoute()

    if (loggedIn && ! isPlatformStaff && isSupportLoginRoute(to))
      return resolveCustomerPostLoginRoute()

    if (loggedIn && isPlatformStaff && ! isPlatformRoute(to) && ! to.meta.public) {
      const customerOnly = ! to.path.startsWith('/admin') && ! to.path.startsWith('/support')

      if (customerOnly && ! to.meta.platformPortal)
        return resolvePlatformPostLoginRoute(userData)
    }

    if (isPlatformStaff) {
      syncManagerBiAbility(useAbility())
    } else {
      syncManagerBiAbility(useAbility())
    }

    if (!canNavigate(to) && to.matched.length) {
      /* eslint-disable indent */
            return loggedIn
                ? { name: 'not-authorized' }
                : isPlatformRoute(to)
                  ? platformLoginRouteForPortal(to.path.includes('/support') ? 'support' : 'admin')
                  : {
                      name: 'login',
                      query: {
                        ...to.query,
                        to: to.fullPath !== '/' ? to.path : undefined,
                      },
                    }
            /* eslint-enable indent */
    }
  })
}
