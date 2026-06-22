import { ofetch } from 'ofetch'
import { router } from '@/plugins/1.router'
import {
  clearAuthSession,
  expireAuthSessionIfNeeded,
  isAuthSessionExpired,
  resolveLoginRouteForExpiredSession,
} from '@/utils/authSession'

let isHandlingUnauthorized = false

function redirectToLoginAfterExpiry() {
  if (isHandlingUnauthorized)
    return

  isHandlingUnauthorized = true

  const loginRoute = resolveLoginRouteForExpiredSession()
  clearAuthSession(null)

  router.replace(loginRoute).finally(() => {
    isHandlingUnauthorized = false
  })
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  async onRequest({ options }) {
    if (isAuthSessionExpired()) {
      redirectToLoginAfterExpiry()
      throw new Error('نشست شما منقضی شده است.')
    }

    const accessToken = useCookie('accessToken').value
    const userData = useCookie('userData').value

    if (accessToken)
      options.headers.set('Authorization', `Bearer ${accessToken}`)

    const tenantId = userData?.tenant?.id ?? userData?.currentTenantId
    const workspaceId = userData?.workspace?.id ?? userData?.currentWorkspaceId

    if (tenantId)
      options.headers.set('X-Tenant-Id', String(tenantId))

    if (workspaceId)
      options.headers.set('X-Workspace-Id', String(workspaceId))
  },
  async onResponseError({ response, request }) {
    if (response.status !== 401)
      return

    const url = String(request)
    if (url.includes('/auth/login') || url.includes('/auth/platform/login') || url.includes('/auth/send-otp') || url.includes('/auth/verify-otp'))
      return

    redirectToLoginAfterExpiry()
  },
})

export function checkAuthSessionExpiry(ability) {
  const loginRoute = expireAuthSessionIfNeeded(ability)

  if (loginRoute)
    router.replace(loginRoute)
}
