export const useCrmCustomerProfile = () => {

  const route = useRoute()

  const router = useRouter()

  const { formatDateTime } = useJalaliDate()

  const { userData } = useAppShell()



  const contactId = computed(() => {
    const raw = route.params.id
    const id = Number(Array.isArray(raw) ? raw[0] : raw)

    return Number.isFinite(id) && id > 0 ? id : null
  })



  const profile = ref(null)

  const loading = ref(true)

  const saving = ref(false)

  const tab = ref('overview')

  const teamUsers = ref([])

  const salesStages = ref([])

  const marketingStages = ref([])



  const fetchProfile = async () => {

    if (!contactId.value)

      return



    loading.value = true

    try {

      profile.value = await $api(`/contacts/${contactId.value}/profile`)

    } catch (e) {

      console.error(e)

      profile.value = null

    } finally {

      loading.value = false

    }

  }



  const fetchMeta = async () => {

    const [usersRes, salesRes, marketingRes] = await Promise.all([

      $api('/users').catch(() => ({ users: [] })),

      $api('/pipeline-stages?type=sales').catch(() => ({ stages: [] })),

      $api('/pipeline-stages?type=marketing').catch(() => ({ stages: [] })),

    ])



    teamUsers.value = usersRes.users ?? []

    salesStages.value = salesRes.stages ?? []

    marketingStages.value = marketingRes.stages ?? []

  }



  watch(contactId, fetchProfile, { immediate: true })



  watch(

    () => route.query.tab,

    value => {

      if (['overview', 'marketing', 'sales', 'activities', 'timeline', 'tasks'].includes(value))

        tab.value = value

    },

    { immediate: true },

  )



  onMounted(fetchMeta)



  const saveContact = async payload => {

    saving.value = true

    try {

      await $api(`/contacts/${contactId.value}`, { method: 'PUT', body: payload })

      await fetchProfile()

    } finally {

      saving.value = false

    }

  }



  const addActivity = async form => {

    await $api('/activities', {

      method: 'POST',

      body: {

        ...form,

        related_type: 'contact',

        related_id: contactId.value,

      },

    })

    await fetchProfile()

  }



  const addTask = async form => {

    await $api('/tasks', {

      method: 'POST',

      body: {

        ...form,

        related_type: 'contact',

        related_id: contactId.value,

      },

    })

    await fetchProfile()

  }



  const completeHandoff = async handoffId => {

    await $api(`/handoffs/${handoffId}/complete`, { method: 'PATCH' })

    await fetchProfile()

  }



  const returnHandoff = async (handoffId, body) => {

    await $api(`/handoffs/${handoffId}/return`, { method: 'POST', body })

    await fetchProfile()

  }



  const formatDt = val => val ? formatDateTime(val) : '—'



  const goToMarketingFunnel = () => router.push({ name: 'apps-crm-marketing-funnel' })



  const goToDeals = (dealId = null) => {
    const id = Number(dealId)

    router.push({
      name: 'apps-crm-deals',
      query: Number.isFinite(id) && id > 0 ? { focus: id, tab: 'sales' } : {},
    })
  }



  const openDealHandoff = deal => ({

    entityType: 'deal',

    entity: { ...deal, pipeline_stage_id: deal.stage?.id },

    stages: salesStages.value,

  })



  const openLeadHandoff = lead => ({

    entityType: 'lead',

    entity: { ...lead, marketing_stage_id: lead.marketing_stage?.id },

    stages: marketingStages.value,

  })



  const currentUserId = computed(() => userData.value?.id ?? null)



  return {

    contactId,

    profile,

    loading,

    saving,

    tab,

    teamUsers,

    salesStages,

    marketingStages,

    currentUserId,

    fetchProfile,

    saveContact,

    addActivity,

    addTask,

    completeHandoff,

    returnHandoff,

    formatDt,

    goToMarketingFunnel,

    goToDeals,

    openDealHandoff,

    openLeadHandoff,

  }

}


