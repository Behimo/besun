export function useCrmLeadPermissions() {
  const { userData } = useAppShell()

  const isOwner = computed(() => Boolean(userData.value?.tenant?.isOwner))
  const permissions = computed(() => userData.value?.permissions ?? [])

  const canViewUnassignedLeads = computed(() =>
    isOwner.value || permissions.value.includes('leads.view_unassigned'),
  )

  const canAssignLeads = computed(() =>
    isOwner.value || permissions.value.includes('leads.assign'),
  )

  return {
    canViewUnassignedLeads,
    canAssignLeads,
  }
}
