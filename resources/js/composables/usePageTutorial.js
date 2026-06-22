const AUTO_OPEN_DELAY_MS = 1200

function sessionGet(key) {
  try {
    return sessionStorage.getItem(key)
  } catch {
    return null
  }
}

function sessionSet(key, value) {
  try {
    sessionStorage.setItem(key, value)
  } catch {
    // ignore
  }
}

function readLegacyDismissedMap(cookieRef) {
  const raw = cookieRef.value

  if (!raw || typeof raw !== 'object' || Array.isArray(raw))
    return {}

  return raw
}

function hasLegacyDismissals(cookieRef) {
  return Object.values(readLegacyDismissedMap(cookieRef)).some(Boolean)
}

export function usePageTutorial() {
  const route = useRoute()
  const { fetchTutorials, resolveTutorial } = usePageTutorialsApi()

  const dismissedGlobally = useCookie('tutorialDismissedAll', {
    default: () => false,
    maxAge: 60 * 60 * 24 * 365,
  })

  const legacyDismissedRoutes = useCookie('tutorialDismissedRoutes', {
    default: () => ({}),
    maxAge: 60 * 60 * 24 * 365,
  })

  onMounted(() => {
    fetchTutorials()
  })

  const routeKey = computed(() => route.name ?? '')
  const tutorial = computed(() => {
    if (!routeKey.value)
      return null

    return resolveTutorial(routeKey.value)
  })
  const hasTutorial = computed(() => Boolean(tutorial.value?.title))

  const isPermanentlyDismissed = computed(() => {
    if (dismissedGlobally.value)
      return true

    return hasLegacyDismissals(legacyDismissedRoutes)
  })

  const isExpanded = ref(false)
  const showFab = ref(false)
  let autoOpenTimer = null

  const clearAutoOpenTimer = () => {
    if (autoOpenTimer) {
      clearTimeout(autoOpenTimer)
      autoOpenTimer = null
    }
  }

  const scheduleAutoOpen = () => {
    clearAutoOpenTimer()

    if (!hasTutorial.value || !routeKey.value)
      return

    if (isPermanentlyDismissed.value) {
      showFab.value = true
      isExpanded.value = false

      return
    }

    const sessionKey = `tutorial-seen-${routeKey.value}`
    const seenThisSession = sessionGet(sessionKey) === '1'

    if (seenThisSession) {
      showFab.value = true
      isExpanded.value = false

      return
    }

    autoOpenTimer = setTimeout(() => {
      if (routeKey.value !== route.name)
        return

      isExpanded.value = true
      showFab.value = false
      sessionSet(sessionKey, '1')
    }, AUTO_OPEN_DELAY_MS)
  }

  watch(routeKey, () => {
    isExpanded.value = false
    showFab.value = false
    scheduleAutoOpen()
  }, { immediate: true })

  onBeforeUnmount(clearAutoOpenTimer)

  const closePanel = () => {
    isExpanded.value = false
    showFab.value = true
  }

  const openPanel = () => {
    isExpanded.value = true
    showFab.value = false
  }

  const dismissPermanently = () => {
    dismissedGlobally.value = true
    isExpanded.value = false
    showFab.value = true
  }

  return {
    tutorial,
    hasTutorial,
    isExpanded,
    showFab,
    isPermanentlyDismissed,
    closePanel,
    openPanel,
    dismissPermanently,
  }
}
