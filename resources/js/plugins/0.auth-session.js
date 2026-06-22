import { checkAuthSessionExpiry } from '@/utils/api'
import { isLoggedIn } from '@/utils/authSession'

export default function (app) {
  let intervalId = null

  const check = () => {
    app.runWithContext(() => {
      checkAuthSessionExpiry(useAbility())
    })
  }

  const refreshAuthPayload = () => {
    app.runWithContext(async () => {
      if (! isLoggedIn())
        return

      const userData = useCookie('userData').value

      if (! userData?.inTenantShell)
        return

      try {
        const res = await $api('/auth/me')

        if (res.userAbilityRules) {
          useCookie('userAbilityRules').value = res.userAbilityRules
          useAbility().update(res.userAbilityRules)
        }

        if (res.userData)
          useCookie('userData').value = { ...res.userData }
      } catch {
        // ignore — stale cookies are still patched by syncManagerBiAbility in router guard
      }
    })
  }

  refreshAuthPayload()
  intervalId = window.setInterval(check, 60 * 1000)

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      check()
      refreshAuthPayload()
    }
  })
}
