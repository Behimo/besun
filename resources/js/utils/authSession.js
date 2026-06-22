import { AUTH_COOKIE_MAX_AGE } from '@core/composable/useCookie'
import { isPlatformStaffSession, platformLoginRouteForPortal } from '@/composables/usePlatformAdmin'

export const AUTH_SESSION_LIFETIME_MS = AUTH_COOKIE_MAX_AGE * 1000

const sessionExpiredQuery = { logout: '1', reason: 'session_expired' }

export function setAuthSession({ accessToken, userData, userAbilityRules }, ability) {
  useCookie('accessToken').value = accessToken
  useCookie('userData').value = userData
  useCookie('userAbilityRules').value = userAbilityRules
  useCookie('authSessionExpiresAt').value = Date.now() + AUTH_SESSION_LIFETIME_MS

  if (ability)
    ability.update(userAbilityRules ?? [])
}

export function clearAuthSession(ability) {
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null
  useCookie('authSessionExpiresAt').value = null

  if (ability)
    ability.update([])
}

export function isAuthSessionExpired() {
  const expiresAt = useCookie('authSessionExpiresAt').value

  if (! expiresAt)
    return Boolean(useCookie('accessToken').value)

  return Date.now() >= Number(expiresAt)
}

export function isLoggedIn() {
  if (isAuthSessionExpired())
    return false

  const accessToken = useCookie('accessToken').value
  const userData = useCookie('userData').value

  return Boolean(accessToken && userData?.id)
}

export function resolveLoginRouteForExpiredSession(userData = useCookie('userData').value) {
  if (isPlatformStaffSession(userData)) {
    const portal = userData?.isPlatformSupport ? 'support' : 'admin'

    return {
      ...platformLoginRouteForPortal(portal),
      query: sessionExpiredQuery,
    }
  }

  return {
    name: 'login',
    query: sessionExpiredQuery,
  }
}

export function expireAuthSessionIfNeeded(ability) {
  if (! useCookie('accessToken').value)
    return null

  if (! isAuthSessionExpired())
    return null

  const loginRoute = resolveLoginRouteForExpiredSession()
  clearAuthSession(ability)

  return loginRoute
}
