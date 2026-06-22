import { getTutorialForRoute, pageTutorials } from '@/data/page-tutorials'

const tutorialsCache = ref(null)
const loading = ref(false)
let fetchPromise = null

export function usePageTutorialsApi() {
  const fetchTutorials = async (force = false) => {
    if (tutorialsCache.value && !force)
      return tutorialsCache.value

    if (fetchPromise && !force)
      return fetchPromise

    loading.value = true
    fetchPromise = $api('/page-tutorials')
      .then(res => {
        tutorialsCache.value = res.tutorials ?? {}

        return tutorialsCache.value
      })
      .catch(() => {
        tutorialsCache.value = {}

        return tutorialsCache.value
      })
      .finally(() => {
        loading.value = false
        fetchPromise = null
      })

    return fetchPromise
  }

  const resolveTutorial = routeName => {
    const fromApi = tutorialsCache.value?.[routeName]

    if (fromApi) {
      if (fromApi.isActive === false)
        return null

      return fromApi
    }

    return getTutorialForRoute(routeName)
  }

  const invalidateCache = () => {
    tutorialsCache.value = null
    fetchPromise = null
  }

  return {
    tutorialsCache,
    loading,
    fetchTutorials,
    resolveTutorial,
    invalidateCache,
    staticCatalog: pageTutorials,
  }
}
