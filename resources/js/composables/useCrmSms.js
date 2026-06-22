export function useCrmSms() {
  const loading = ref(false)
  const error = ref('')

  const fetchDashboard = async () => {
    loading.value = true
    error.value = ''
    try {
      return await $api('/sms/dashboard')
    } catch (e) {
      error.value = e?.data?.message || 'خطا در بارگذاری پنل پیامک'
      throw e
    } finally {
      loading.value = false
    }
  }

  const fetchNumbers = async () => {
    const res = await $api('/sms/numbers')

    return res
  }

  const fetchMessages = async (page = 1) => {
    return await $api('/sms/messages', { query: { page } })
  }

  const fetchTemplates = async () => {
    const res = await $api('/sms/templates')

    return res.templates ?? []
  }

  const previewAudience = async filters => {
    return await $api('/sms/send/preview', { method: 'POST', body: filters })
  }

  const sendSms = async payload => {
    return await $api('/sms/send', { method: 'POST', body: payload })
  }

  const fetchCreditPackages = async () => {
    const res = await $api('/sms/credit/packages')

    return res.packages ?? []
  }

  const purchaseCredit = async packageId => {
    return await $api('/sms/credit/purchase', {
      method: 'POST',
      body: { package_id: packageId },
    })
  }

  const saveTemplate = async body => {
    return await $api('/sms/templates', { method: 'POST', body })
  }

  return {
    loading,
    error,
    fetchDashboard,
    fetchNumbers,
    fetchMessages,
    fetchTemplates,
    previewAudience,
    sendSms,
    fetchCreditPackages,
    purchaseCredit,
    saveTemplate,
  }
}
