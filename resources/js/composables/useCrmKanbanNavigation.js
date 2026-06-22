export const useCrmKanbanNavigation = () => {
  const router = useRouter()

  const resolveContactId = async (item, variant) => {
    let contactId = item?.contact_id ?? item?.contact?.id

    if (contactId || !item?.id)
      return contactId

    try {
      const endpoint = variant === 'marketing'
        ? `/leads/${item.id}`
        : `/deals/${item.id}`

      const res = await $api(endpoint)
      const entity = res.lead ?? res.deal

      contactId = entity?.contact_id ?? entity?.contact?.id
    } catch (error) {
      console.error(error)
    }

    return contactId
  }

  const openCustomerProfile = async (item, variant = 'sales') => {
    const contactId = await resolveContactId(item, variant)

    if (!contactId)
      return

    router.push({
      name: 'apps-crm-contacts-id',
      params: { id: contactId },
    })
  }

  const onKanbanSelect = async ({ item, variant }) => {
    const contactId = await resolveContactId(item, variant)

    if (!contactId)
      return

    const query = {}

    if (variant === 'marketing')
      query.tab = 'marketing'
    else if (variant === 'sales')
      query.tab = 'sales'

    router.push({
      name: 'apps-crm-contacts-id',
      params: { id: contactId },
      query,
    })
  }

  return {
    openCustomerProfile,
    onKanbanSelect,
  }
}
