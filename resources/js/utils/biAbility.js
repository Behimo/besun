export function syncManagerBiAbility(ability) {
  const userData = useCookie('userData').value

  if (! userData?.inTenantShell)
    return

  const role = userData.role ?? ''
  const isOwner = userData.tenant?.isOwner || role === 'owner'
  const isManager = isOwner || Boolean(userData.isManager)

  const rulesCookie = useCookie('userAbilityRules')
  const rules = rulesCookie.value ?? []
  const hasManageAll = rules.some(r => r.action === 'manage' && r.subject === 'all')

  if (isOwner && ! hasManageAll) {
    const updated = [
      { action: 'manage', subject: 'all' },
      { action: 'manage', subject: 'TenantSettings' },
    ]

    rulesCookie.value = updated

    if (ability)
      ability.update(updated)

    return
  }

  if (! isManager)
    return

  const hasBi = rules.some(r => r.action === 'read' && r.subject === 'BI')

  if (hasBi || hasManageAll)
    return

  const updated = [...rules, { action: 'read', subject: 'BI' }]

  rulesCookie.value = updated

  if (ability)
    ability.update(updated)
}
