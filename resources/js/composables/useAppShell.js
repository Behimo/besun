export const useAppShell = () => {
  const userData = useCookie('userData')
  const ability = useAbility()
  const router = useRouter()

  const isInTenantShell = computed(() => userData.value?.inTenantShell === true && Boolean(userData.value?.tenant?.id))
  const hasCoreModule = computed(() => Boolean(userData.value?.hasCoreModule))
  const activeModules = computed(() => userData.value?.activeModules ?? [])

  const applyAuthPayload = payload => {
    if (payload.userAbilityRules) {
      useCookie('userAbilityRules').value = payload.userAbilityRules
      ability.update(payload.userAbilityRules)
    }

    if (payload.userData)
      userData.value = { ...payload.userData }
  }

  const enterTenantShell = async tenant => {
    const res = await $api(`/tenants/${tenant.id}/switch`, { method: 'POST' })
    applyAuthPayload(res)
    await nextTick()

    if (res.redirect)
      await router.replace({ name: res.redirect })

    return res
  }

  const exitTenantShell = async () => {
    try {
      const res = await $api('/tenants/exit', { method: 'POST' })

      applyAuthPayload(res)
      await nextTick()
      await router.replace({ name: 'dashboards-home' })

      return res
    } catch (e) {
      console.error(e)
      await router.replace({ name: 'dashboards-home' })

      throw e
    }
  }

  const hasModule = slug => activeModules.value.includes(slug)

  const isPlatformAdmin = computed(() => userData.value?.authType === 'platform_staff' && Boolean(userData.value?.isPlatformAdmin))
  const isPlatformSupport = computed(() => userData.value?.authType === 'platform_staff' && Boolean(userData.value?.isPlatformSupport))
  const isPlatformSuperAdmin = computed(() => userData.value?.authType === 'platform_staff' && Boolean(userData.value?.isPlatformSuperAdmin))
  const isPlatformStaff = computed(() => userData.value?.authType === 'platform_staff')
  const isInPlatformShell = computed(() => isPlatformStaff.value && !isInTenantShell.value)

  return {
    userData,
    isInTenantShell,
    isInPlatformShell,
    isPlatformAdmin,
    isPlatformSupport,
    isPlatformSuperAdmin,
    isPlatformStaff,
    hasCoreModule,
    activeModules,
    applyAuthPayload,
    enterTenantShell,
    exitTenantShell,
    hasModule,
  }
}
