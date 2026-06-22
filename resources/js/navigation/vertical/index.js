import ownerNav from './owner-nav'
import { buildTenantNav } from './tenant-nav'

export function getNavItems(userData, inTenantShell = null) {
  const inShell = inTenantShell ?? (userData?.inTenantShell === true && Boolean(userData?.tenant?.id))

  if (inShell)
    return buildTenantNav(userData)

  return ownerNav
}

export default ownerNav
