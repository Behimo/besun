export function useCrmAutomation() {
  const loading = ref(false)
  const error = ref('')

  const withLoading = async (fn, fallbackMessage) => {
    loading.value = true
    error.value = ''
    try {
      return await fn()
    } catch (e) {
      error.value = e?.data?.message || fallbackMessage
      throw e
    } finally {
      loading.value = false
    }
  }

  const fetchDashboard = async () => {
    return await withLoading(
      () => $api('/automation/dashboard'),
      'خطا در بارگذاری داشبورد اتوماسیون',
    )
  }

  const fetchMeta = async () => {
    return await withLoading(
      () => $api('/automation/meta'),
      'خطا در بارگذاری اطلاعات فرم',
    )
  }

  const fetchRules = async () => {
    const res = await withLoading(
      () => $api('/automation/rules'),
      'خطا در بارگذاری قوانین',
    )

    return res.rules ?? []
  }

  const fetchRule = async id => {
    const res = await $api(`/automation/rules/${id}`)

    return res.rule
  }

  const saveRule = async (payload, id = null) => {
    if (id) {
      const res = await $api(`/automation/rules/${id}`, { method: 'PATCH', body: payload })

      return res.rule
    }

    const res = await $api('/automation/rules', { method: 'POST', body: payload })

    return res.rule
  }

  const deleteRule = async id => {
    return await $api(`/automation/rules/${id}`, { method: 'DELETE' })
  }

  const toggleRule = async id => {
    const res = await $api(`/automation/rules/${id}/toggle`, { method: 'PATCH' })

    return res.rule
  }

  const fetchRuns = async (page = 1, status = null) => {
    return await withLoading(
      () => $api('/automation/runs', { query: { page, status } }),
      'خطا در بارگذاری لاگ اجرا',
    )
  }

  return {
    loading,
    error,
    fetchDashboard,
    fetchMeta,
    fetchRules,
    fetchRule,
    saveRule,
    deleteRule,
    toggleRule,
    fetchRuns,
  }
}
