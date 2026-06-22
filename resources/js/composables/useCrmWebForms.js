export function useCrmWebForms() {
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
      () => $api('/web-forms/dashboard'),
      'خطا در بارگذاری داشبورد وب‌فرم',
    )
  }

  const fetchForms = async () => {
    const res = await withLoading(
      () => $api('/web-forms'),
      'خطا در بارگذاری فرم‌ها',
    )

    return res.forms ?? []
  }

  const fetchForm = async id => {
    const res = await $api(`/web-forms/${id}`)

    return res.form
  }

  const saveForm = async (payload, id = null) => {
    if (id) {
      const res = await $api(`/web-forms/${id}`, { method: 'PATCH', body: payload })

      return res.form
    }

    const res = await $api('/web-forms', { method: 'POST', body: payload })

    return res.form
  }

  const deleteForm = async id => {
    return await $api(`/web-forms/${id}`, { method: 'DELETE' })
  }

  const fetchSubmissions = async (formId, page = 1) => {
    return await withLoading(
      () => $api(`/web-forms/${formId}/submissions`, { query: { page } }),
      'خطا در بارگذاری پاسخ‌ها',
    )
  }

  const fetchFormReport = async formId => {
    return await withLoading(
      () => $api(`/web-forms/${formId}/report`),
      'خطا در بارگذاری گزارش فرم',
    )
  }

  const fetchPublicForm = async token => {
    const res = await $api(`/forms/${token}`)

    return res.form
  }

  const submitPublicForm = async (token, payload) => {
    return await $api(`/forms/${token}/submit`, {
      method: 'POST',
      body: { payload },
    })
  }

  return {
    loading,
    error,
    fetchDashboard,
    fetchForms,
    fetchForm,
    saveForm,
    deleteForm,
    fetchSubmissions,
    fetchFormReport,
    fetchPublicForm,
    submitPublicForm,
  }
}
